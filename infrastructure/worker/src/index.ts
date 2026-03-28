import { DbAdapter } from './db';
import { normalizeDomain, parseJsonBody, validateLicenseSchema } from './validation';
import type { Env, ValidateLicenseRequest } from './types';

function json(data: unknown, status = 200): Response {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      'content-type': 'application/json; charset=utf-8'
    }
  });
}

function isExpired(expiresAt: string | null): boolean {
  if (!expiresAt) {
    return false;
  }

  return new Date(expiresAt).getTime() <= Date.now();
}

async function handleHealth(db: DbAdapter): Promise<Response> {
  try {
    await db.ping();
    return json({ ok: true });
  } catch (error) {
    return json({ ok: false, error: (error as Error).message }, 500);
  }
}

async function handleValidateLicense(request: Request, db: DbAdapter): Promise<Response> {
  const input = await parseJsonBody<ValidateLicenseRequest>(request, validateLicenseSchema);
  const normalizedDomain = normalizeDomain(input.domain);

  const license = await db.findLicenseForValidation(input.license_key, input.product_slug);

  if (!license) {
    await db.writeAuditLog('license.validation.failed', 'worker', null, {
      reason: 'not_found',
      product_slug: input.product_slug,
      instance_id: input.instance_id,
      domain: normalizedDomain
    });

    return json(
      {
        valid: false,
        status: 'not_found',
        message: 'License key was not found for this product.',
        features: []
      },
      404
    );
  }

  if (!['active', 'trialing', 'past_due'].includes(license.status)) {
    await db.writeAuditLog('license.validation.failed', 'worker', license.license_id, {
      reason: 'inactive_status',
      status: license.status,
      instance_id: input.instance_id,
      domain: normalizedDomain
    });

    return json(
      {
        valid: false,
        status: license.status,
        message: 'License is not active.',
        features: []
      },
      403
    );
  }

  if (isExpired(license.expires_at)) {
    await db.setLicenseExpired(license.license_id);
    await db.writeAuditLog('license.validation.failed', 'worker', license.license_id, {
      reason: 'expired',
      instance_id: input.instance_id,
      domain: normalizedDomain
    });

    return json(
      {
        valid: false,
        status: 'expired',
        message: 'License has expired.',
        features: []
      },
      403
    );
  }

  const currentActivations = await db.countActiveActivations(license.license_id);
  const activationExists = await db.activationExists(license.license_id, input.instance_id, normalizedDomain);

  if (!activationExists && currentActivations >= license.max_activations) {
    await db.writeAuditLog('license.validation.failed', 'worker', license.license_id, {
      reason: 'activation_limit_reached',
      max_activations: license.max_activations,
      current_activations: currentActivations,
      instance_id: input.instance_id,
      domain: normalizedDomain
    });

    return json(
      {
        valid: false,
        status: 'activation_limit_reached',
        message: 'Maximum activations reached for this license.',
        max_activations: license.max_activations,
        current_activations: currentActivations,
        features: []
      },
      403
    );
  }

  await db.upsertActivation(license.license_id, input.instance_id, normalizedDomain);

  const activationCountAfter = activationExists ? currentActivations : currentActivations + 1;

  await db.writeAuditLog('license.validation.succeeded', 'worker', license.license_id, {
    status: license.status,
    instance_id: input.instance_id,
    domain: normalizedDomain,
    current_activations: activationCountAfter
  });

  return json({
    valid: true,
    status: license.status,
    plan_slug: license.plan_slug,
    features: license.features,
    max_activations: license.max_activations,
    current_activations: activationCountAfter,
    expires_at: license.expires_at,
    message: 'License valid.'
  });
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    const pathname = url.pathname.replace(/\/+$/, '') || '/';
    const method = request.method.toUpperCase();
    const db = new DbAdapter(env.DATABASE_URL);

    try {
      if (method === 'GET' && pathname === '/health') {
        return await handleHealth(db);
      }

      if (method === 'POST' && pathname === '/validate-license') {
        return await handleValidateLicense(request, db);
      }

      return json({ error: 'Not found' }, 404);
    } catch (error) {
      return json({ error: (error as Error).message }, 400);
    }
  }
};
