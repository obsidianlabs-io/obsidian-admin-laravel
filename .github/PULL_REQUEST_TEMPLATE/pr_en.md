First of all, thank you for your contribution.

This repository is a production-grade Laravel backend baseline. Please keep the pull request narrow, explicit, and technically defensible.

[[中文版模板 / Chinese template](./pr_cn.md)]

### Change Type

- [ ] New feature
- [ ] Bug fix
- [ ] Refactor
- [ ] Documentation update
- [ ] CI / tooling change
- [ ] Performance / runtime change
- [ ] Security hardening
- [ ] Contract / OpenAPI change
- [ ] Other

### Background

> Explain the original problem, requirement, or issue link.

### Solution

> Describe the implementation and any important architectural decisions.

### Contract / Runtime Impact

- [ ] No API contract change
- [ ] OpenAPI / response shape changed
- [ ] Migration or seed behavior changed
- [ ] Queue / cache / Redis behavior changed
- [ ] Tenant / RBAC / auth behavior changed
- [ ] Octane / RoadRunner / Reverb behavior changed

> Add short notes if any item above is checked.

### Validation

- [ ] `vendor/bin/pint --test`
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G`
- [ ] `php artisan test`
- [ ] Relevant DB matrix tests run when needed
- [ ] OpenAPI / contract snapshot updated when needed

### Risk Notes

> Mention breakage risk, rollback concerns, upgrade concerns, or operational impact.

### Additional Context

> Add screenshots, traces, benchmark notes, or follow-up items if needed.
