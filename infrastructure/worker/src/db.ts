import { Pool, type QueryResultRow } from 'pg';
import type { LicenseValidationRow } from './types';

let globalPool: Pool | null = null;

function toStringArray(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.map((item) => String(item));
  }

  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) {
        return parsed.map((item) => String(item));
      }
    } catch {
      return [];
    }
  }

  return [];
}

export class DbAdapter {
  private readonly pool: Pool;

  constructor(databaseUrl: string) {
    if (!globalPool) {
      globalPool = new Pool({
        connectionString: databaseUrl,
        max: 5,
        ssl: {
          rejectUnauthorized: false
        }
      });
    }

    this.pool = globalPool;
  }

  async ping(): Promise<boolean> {
    await this.pool.query('select 1');
    return true;
  }

  async findLicenseForValidation(licenseKey: string, productSlug: string): Promise<LicenseValidationRow | null> {
    const result = await this.pool.query<QueryResultRow>(
      `
      select
        l.id as license_id,
        l.license_key,
        l.status,
        l.expires_at,
        p.slug as plan_slug,
        p.max_activations,
        p.features
      from licenses l
      inner join products pr on pr.id = l.product_id
      inner join plans p on p.id = l.plan_id
      where l.license_key = $1
        and pr.slug = $2
      limit 1
      `,
      [licenseKey, productSlug]
    );

    if (!result.rows[0]) {
      return null;
    }

    const row = result.rows[0];

    return {
      license_id: String(row.license_id),
      license_key: String(row.license_key),
      status: String(row.status),
      expires_at: row.expires_at ? String(row.expires_at) : null,
      plan_slug: String(row.plan_slug),
      max_activations: Number(row.max_activations),
      features: toStringArray(row.features)
    };
  }

  async countActiveActivations(licenseId: string): Promise<number> {
    const result = await this.pool.query<{ total: string }>(
      `
      select count(*)::text as total
      from activations
      where license_id = $1
        and deactivated_at is null
      `,
      [licenseId]
    );

    return Number(result.rows[0]?.total ?? '0');
  }

  async activationExists(licenseId: string, instanceId: string, domain: string): Promise<boolean> {
    const result = await this.pool.query<{ found: number }>(
      `
      select 1 as found
      from activations
      where license_id = $1
        and instance_id = $2::uuid
        and domain = $3
        and deactivated_at is null
      limit 1
      `,
      [licenseId, instanceId, domain]
    );

    return Boolean(result.rows[0]);
  }

  async upsertActivation(licenseId: string, instanceId: string, domain: string): Promise<void> {
    await this.pool.query(
      `
      insert into activations (
        license_id,
        instance_id,
        domain,
        first_activated_at,
        last_validated_at,
        deactivated_at
      )
      values ($1, $2::uuid, $3, now(), now(), null)
      on conflict (license_id, instance_id, domain)
      do update set
        last_validated_at = now(),
        deactivated_at = null
      `,
      [licenseId, instanceId, domain]
    );
  }

  async setLicenseExpired(licenseId: string): Promise<void> {
    await this.pool.query(
      `
      update licenses
      set status = 'expired',
          updated_at = now()
      where id = $1
      `,
      [licenseId]
    );
  }

  async writeAuditLog(action: string, actor: string, licenseId: string | null, context: Record<string, unknown>): Promise<void> {
    await this.pool.query(
      `
      insert into audit_log (action, actor, license_id, context)
      values ($1, $2, $3, $4::jsonb)
      `,
      [action, actor, licenseId, JSON.stringify(context)]
    );
  }
}
