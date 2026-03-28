import { z } from 'zod';

const uuidV4Regex =
  /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

const productSlug = z
  .string()
  .min(2)
  .max(100)
  .regex(/^[a-z0-9-]+$/);

export const validateLicenseSchema = z.object({
  product_slug: productSlug,
  license_key: z.string().min(8).max(200),
  instance_id: z.string().regex(uuidV4Regex),
  domain: z.string().min(1).max(255)
});

export function normalizeDomain(input: string): string {
  const candidate = input.trim().toLowerCase();
  const maybeUrl = candidate.startsWith('http') ? candidate : `https://${candidate}`;

  let host = '';
  try {
    host = new URL(maybeUrl).hostname;
  } catch {
    host = candidate;
  }

  if (host.startsWith('www.')) {
    host = host.slice(4);
  }

  return host;
}

export async function parseJsonBody<T>(request: Request, schema: z.Schema<T>): Promise<T> {
  let payload: unknown;

  try {
    payload = await request.json();
  } catch {
    throw new Error('Invalid JSON body');
  }

  const parsed = schema.safeParse(payload);

  if (!parsed.success) {
    const firstIssue = parsed.error.issues[0];
    throw new Error(`Validation error: ${firstIssue?.path.join('.') ?? 'payload'} ${firstIssue?.message ?? ''}`.trim());
  }

  return parsed.data;
}
