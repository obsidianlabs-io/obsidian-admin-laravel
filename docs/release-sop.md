# Release SOP

This document defines the release process for `obsidian-admin-laravel`.

目标是让每次 release 都遵循同一套流程，避免“代码通过了，但 tag、Release、GitHub 设置没跟上”的问题。

## 1. Pre-Release Rules

发布前先确认以下原则:

- release tag 只指向代码发布提交
- 文档补充提交不应该强行回写到已发布 tag
- `main` 必须是绿色状态
- working tree 必须干净

## 2. Prepare Release Content

在创建 tag 之前，先完成这些内容:

- 更新 `CHANGELOG.md`
- 准备当前版本 release note:
  `docs/releases/vX.Y.Z.md`
- 如有需要，更新仓库元信息:
  `docs/github/repository-metadata.md`

## 3. Required Release Gates

在 backend release 之前，必须手动确认以下命令全部通过:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

同时确认 GitHub Actions 的 `Backend Supply Chain` 为绿色，并且已生成 `backend-sbom-cyclonedx` artifact 与对应 attestation。

如果你修改了 workflow、OpenAPI、代理配置或安全策略，建议额外确认:

```bash
php artisan openapi:lint
php artisan security:baseline
php artisan http:proxy-trust-check --strict
```

如果你修改了 Octane、RoadRunner、Docker、运行时扩展或 worker 生命周期相关逻辑，建议额外做一次本地 smoke：

```bash
php artisan octane:start --server=roadrunner
curl --fail --silent http://127.0.0.1:8000/api/health/live
php artisan octane:stop --server=roadrunner
```

如果你修改了 Dockerfile、compose 文件或 runtime PHP extensions，也建议额外确认 production compose：

```bash
APP_HTTP_PORT=18080 REVERB_PUBLIC_PORT=16001 docker compose -f docker-compose.production.yml up -d --build mysql redis app nginx
curl --fail --silent http://127.0.0.1:18080/api/health/live
docker compose -f docker-compose.production.yml down -v
```

如果你修改了最终 app image、entrypoint、PHP-FPM 启动命令或 bootstrap cache 行为，也建议额外确认 cold boot：

```bash
docker build -t obsidian-admin-laravel:image-smoke .
cat <<'PHP' > /tmp/image-smoke-probe.php
<?php
require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/health/live', 'GET');
$response = $kernel->handle($request);
$content = $response->getContent() ?: '';

fwrite(STDOUT, $content.PHP_EOL);

$kernel->terminate($request, $response);

if ($response->getStatusCode() !== 200 || !str_contains($content, '"status":"alive"')) {
    fwrite(STDERR, "live probe failed\n");
    exit(1);
}
PHP
docker run -d --name obsidian-admin-laravel-image-smoke \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_KEY=base64:Q2qE5A3yM4tQvL3X0yr7M5m4r2m40fX9zCw1Q2m3N4o= \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e AUDIT_QUEUE_CONNECTION=sync \
  -e LOG_CHANNEL=stderr \
  -e LOG_STACK=stderr \
  obsidian-admin-laravel:image-smoke
docker inspect -f '{{.State.Running}}' obsidian-admin-laravel-image-smoke
docker cp /tmp/image-smoke-probe.php obsidian-admin-laravel-image-smoke:/tmp/image-smoke-probe.php
docker exec obsidian-admin-laravel-image-smoke php /tmp/image-smoke-probe.php
docker rm -f obsidian-admin-laravel-image-smoke
docker image rm obsidian-admin-laravel:image-smoke
```

## 4. Check Repository State

确认当前仓库状态:

```bash
git status --short
git log --oneline -3
git tag --list --sort=version:refname
```

标准:

- `git status --short` 必须为空
- 当前 `HEAD` 必须是你要发布的提交

## 5. Push Main First

先推送 `main`，再打 release tag:

```bash
git push origin main
```

原因:

- 让 CI 先对最新 `main` 生效
- 避免 tag 指向一个还没推到远端的提交

## 6. Create Release Tag

创建 annotated tag:

```bash
git tag -a vX.Y.Z -m "vX.Y.Z"
git push origin vX.Y.Z
```

规则:

- 不要用 lightweight tag
- 不要让 tag 指向后补的文档提交，除非你明确要把那次文档纳入版本产物

## 7. Publish GitHub Release

在 GitHub 上创建 Release 时:

- Tag 选择: `vX.Y.Z`
- Title 使用:
  `docs/github/repository-metadata.md`
- Body 使用:
  `docs/releases/vX.Y.Z.md`

自动发布 workflow 也遵循同一规则：

- 优先使用 `docs/releases/vX.Y.Z.md`
- 只有在该文件缺失时，才回退到 `CHANGELOG.md`

## 8. Update Repository Metadata

每次正式 release 前后，确认这些设置没有漂移:

- About
- Description
- Topics
- Branch protection
- Required status checks
- Actions permissions

参考:

- `docs/github/repository-setup-checklist.md`

## 9. Post-Release Check

发布完成后，至少确认:

- `main` 和 tag 都已推送
- GitHub Release 已可见
- CI 没有在 `main` 或 tag 上出现新失败
- 当前 release note 与 changelog 版本号一致

## 10. Quick Checklist

发布当天只看这一段也够用:

1. 更新 `CHANGELOG.md`
2. 准备 `docs/releases/vX.Y.Z.md`
3. 跑 `pint + phpstan + test`
4. 确认工作区干净
5. 推 `main`
6. 打 tag 并推
7. 创建 GitHub Release
8. 检查 About / Topics / protection rules

## 11. Final Sign-Off

For the last pre-release pass, use:

- `docs/release-final-checklist.md`
