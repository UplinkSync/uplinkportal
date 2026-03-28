# API Reference

## REST Namespace

- `uplinkportal/v1`

## Core Routes

- `GET /uplinkportal/v1/me`
- `GET /uplinkportal/v1/modules`
- `GET /uplinkportal/v1/data/{module}`
- `GET /uplinkportal/v1/client-profile`
- `POST /uplinkportal/v1/client-profile`

## Security Notes

- Routes require authenticated users with `client_access` capability.
- REST nonce enforcement is enabled by default and can be tuned with `uplinkportal_enforce_rest_nonce`.
