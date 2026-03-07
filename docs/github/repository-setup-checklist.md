# GitHub Repository Setup Checklist

This checklist is for `/Users/zero/Documents/Project/WK/obsidian-admin-laravel`.

目标不是把 GitHub 选项全部打开，而是把这个仓库真正需要的设置一次配对，避免发布前遗漏。

## 1. Repository Profile

在 GitHub 仓库首页右侧点击 `About` -> `Edit repository details`:

- Description:
  `Obsidian Admin Laravel is a production-ready Laravel 12 backend template built for enterprise admin systems, internal platforms, and SaaS products. It emphasizes strict typing, domain-driven structure, tenant-safe data isolation, auditability, and long-term maintainability.`
- About:
  `Strictly-typed Laravel 12 admin backend with DDD boundaries, multi-tenancy, audit logs, OpenAPI generation, and official Octane integration.`
- Topics:
  `laravel laravel12 php php84 api backend ddd clean-architecture multi-tenancy saas rbac audit-logs openapi scramble sanctum roadrunner octane redis horizon reverb pest phpstan deptrac postgresql mysql`
- Website:
  留空即可，除非你后面有正式官网或文档站。
- Include in the home page:
  建议开启 `Releases`、`Packages` 关闭、`Environments` 按需。

来源文案:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/github/repository-metadata.md`

## 2. Default Branch

在 `Settings` -> `Branches`:

- Default branch:
  `main`

## 3. Branch Protection

在 `Settings` -> `Branches` -> `Add branch protection rule`:

- Branch name pattern:
  `main`
- Require a pull request before merging:
  开启
- Require approvals:
  建议至少 `1`
- Dismiss stale pull request approvals when new commits are pushed:
  开启
- Require review from code owners:
  开启
- Require status checks to pass before merging:
  开启
- Require branches to be up to date before merging:
  开启
- Require conversation resolution before merging:
  开启
- Do not allow bypassing the above settings:
  如果是正式团队仓库，建议开启
- Allow force pushes:
  关闭
- Allow deletions:
  关闭

## 4. Required Status Checks

建议至少勾选以下 checks:

- `CI / quality`
- `CI / octane-smoke`
- `CI / tests-sqlite`
- `CI / tests-mysql`
- `CI / tests-pgsql`
- `Quality Gate / Frontend API Contract Typecheck`

不建议同时把下面这个也设为 required:

- `Quality Gate / Backend Quality (Pint + Larastan + Pest + Deptrac)`

原因:

- 它和 `CI / quality` 有较高重复度
- 如果两者都设成 required，PR 等待时间会增加，但收益不成比例

如果你希望 PR 到 `main` 时做“更硬”的门禁，可以反过来只要求下面两个 workflow 结果:

- `Quality Gate / Backend Quality (Pint + Larastan + Pest + Deptrac)`
- `Quality Gate / Frontend API Contract Typecheck`

但那样会放松 SQLite/MySQL/PostgreSQL 三套数据库回归测试，不建议。

说明:

- `CI / octane-smoke` 用于验证官方 Octane + RoadRunner 启动链路没有回退
- 如果你的发布叙事继续包含 `official Octane integration`，建议把它设为 required

对应 workflow:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/.github/workflows/ci.yml`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/.github/workflows/quality.yml`

## 4.5. CODEOWNERS

当前仓库已包含：

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/.github/CODEOWNERS`

当前使用维护邮箱作为默认 owner，这样语法有效、不会依赖一个尚未创建的 GitHub team。

如果后面你建立了正式团队，再把 owner 替换成：

- `@obsidianlabs-io/<team-name>`

## 5. Actions Permissions

在 `Settings` -> `Actions` -> `General`:

- Actions permissions:
  `Allow all actions and reusable workflows`
- Workflow permissions:
  `Read and write permissions`

原因:

- `release.yml` 需要创建 GitHub Release
- `supply-chain.yml` 需要 attestation / dependency review 相关权限

## 6. Repository Variables And Secrets

这个仓库当前是公开仓库，frontend 也是公开仓库。

当前建议:

- Repository variable `FRONTEND_REPO`:
  可选，默认值已经写死为 `obsidianlabs-io/obsidian-admin-vue`
- `FRONTEND_REPO_TOKEN`:
  不需要

说明:

- 之前失败是因为 workflow 依赖了 secret 判断逻辑
- 现在 public frontend 方案已经修过，不应该再要求这个 secret

## 7. Security Settings

在 `Settings` -> `Security`:

- Dependency graph:
  开启
- Dependabot alerts:
  开启
- Dependabot security updates:
  开启
- Secret scanning:
  公共仓库建议开启
- Push protection:
  建议开启

## 8. Releases

推荐发布方式:

- Tag 指向代码发布提交，不强行追着文档提交移动
- GitHub Release 正文使用:
  `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/releases/v1.2.0.md`
- CHANGELOG 使用:
  `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/CHANGELOG.md`

当前状态:

- `v1.2.0` tag 指向 `81d95bf`
- 这是合理的，因为它对应代码发布提交

## 9. Organization Profile

如果你要让组织主页显示说明，不应该改业务仓库，而应该在 organization 下创建专门的 `.github` 仓库:

- repo name:
  `.github`
- target file:
  `.github/profile/README.md`

可直接复制这个模板:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/github/profile/README.md`

## 10. Recommended Cleanup Choices

以下选项建议按“少而稳”原则处理:

- Discussions:
  如果你还没有维护计划，先关闭
- Projects:
  如果不用 GitHub Projects，先关闭
- Wiki:
  如果文档都在仓库里，先关闭
- Packages:
  如果当前不发布 package，先隐藏

## 11. Release-Day Quick Checklist

发布当天只检查这些:

- `main` 是绿色
- branch protection 已生效
- required checks 没有漏选
- `CHANGELOG.md` 已更新
- `docs/releases/<version>.md` 已准备好
- tag 指向的是代码提交，不是临时文档提交
- GitHub Release 正文已贴入
- About / Topics / Description 已更新

## 12. Recommended Next Step

如果你要把这一套真正落地，建议顺序是:

1. 先填 `About / Description / Topics`
2. 再设置 `Branch protection`
3. 再勾 `Required status checks`
4. 再检查 `Actions permissions`
5. 最后创建 GitHub Release

内部发布流程文档:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/release-sop.md`
