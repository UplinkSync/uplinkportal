export interface Env {
  DATABASE_URL: string;
}

export interface ValidateLicenseRequest {
  product_slug: string;
  license_key: string;
  instance_id: string;
  domain: string;
}

export interface LicenseValidationRow {
  license_id: string;
  license_key: string;
  status: string;
  expires_at: string | null;
  plan_slug: string;
  max_activations: number;
  features: string[];
}
