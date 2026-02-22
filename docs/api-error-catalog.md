# API Error Catalog

This project uses a business-code response envelope for all API responses:

```json
{
  "code": "1003",
  "msg": "Forbidden",
  "data": {},
  "requestId": "177f2e17-c4ad-4976-b681-fd3230884f15",
  "traceId": "44ad0b87d4a14bf19f9be41ce53bff7a"
}
```

## Standard Codes

| Code | Meaning | Typical Scenario |
| --- | --- | --- |
| `0000` | Success | Request completed successfully |
| `1001` | Login/credential validation failed | Invalid username/password or auth form error |
| `1002` | Parameter/validation error | Form request validation failed |
| `1003` | Forbidden | Authenticated but missing permission |
| `1009` | Conflict | Optimistic lock/version mismatch |
| `4040` | Not found | Route/model not found |
| `4050` | Method not allowed | Wrong HTTP method for endpoint |
| `4290` | Too many requests | Throttle / rate-limit triggered |
| `5000` | Server error | Unhandled backend exception |
| `8888` | Unauthorized | Missing/invalid authentication |
| `9999` | Token expired | Access token expired |

## Notes

- API keeps HTTP status `200` and uses `code` for business state in current design.
- `requestId` and `traceId` should be logged by frontend for support/debugging.
- For OpenAPI consumers, see `docs/openapi.yaml` (`x-error-catalog`, `components.schemas.ApiErrorResponse`).
