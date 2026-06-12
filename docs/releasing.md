# Releasing dbAPI

How to cut a new **product release** of dbAPI using [Semantic Versioning](https://semver.org/) and git tags.

> **Note:** Product releases (`v1.0.1`, `v1.1.0`, …) are separate from **HTTP API paths** such as `/v1/apis/...` and `/mgmt/v1/...`. URL version prefixes describe the API contract; git tags describe the dbAPI software you ship.

---

## What a release includes

| Artifact | Purpose |
|----------|---------|
| [`VERSION`](../VERSION) | Current semver (`1.0.1`) — single source of truth for the product version |
| [`CHANGELOG.md`](../CHANGELOG.md) | Human-readable release notes |
| Git tag `vX.Y.Z` | Annotated tag pointing at the release commit |
| GitHub Release (optional) | Published notes on GitHub for adopters |
| Docker image (automatic) | Built and pushed when a `v*.*.*` tag is pushed |

---

## Version numbering

| Bump | Example | Use when |
|------|---------|----------|
| **patch** | `1.0.0` → `1.0.1` | Bug fixes, docs, internal refactors — no breaking changes |
| **minor** | `1.0.1` → `1.1.0` | New features, backward-compatible behavior |
| **major** | `1.1.0` → `2.0.0` | Breaking changes (config layout, removed endpoints, incompatible defaults) |

Tags always use a **`v` prefix**: `v1.0.1`.

---

## Release script

All releases go through [`scripts/release.sh`](../scripts/release.sh).

```bash
./scripts/release.sh --help
```

### What the script does

1. Reads the current version from `VERSION` (falls back to the latest `v*` git tag)
2. Bumps or sets the new version
3. Prepends an entry to `CHANGELOG.md` (unless `--skip-changelog`)
4. Commits `VERSION` and `CHANGELOG.md` with message `Release vX.Y.Z`
5. Creates an **annotated** git tag `vX.Y.Z`
6. Optionally pushes the branch and tag to `origin` (`--push`)

---

## Standard release workflow

### 1. Prepare

- Finish and **commit** all changes intended for the release on `master` (or your release branch).
- Run tests:

```bash
cd src && composer install && composer test:unit
# Optional: full integration / Docker suites — see README and test plans
```

The script refuses to run on a dirty working tree unless you pass `--allow-dirty` (avoid that for normal releases).

### 2. Preview

```bash
./scripts/release.sh --dry-run patch -m "Short summary of this release"
```

Dry-run prints planned actions and **does not** modify files, commit, or tag.

### 3. Create the release

```bash
./scripts/release.sh patch -m "Short summary of this release" --push
```

Replace `patch` with `minor` or `major` as appropriate.

Without `--push`, the script prints the manual push commands at the end:

```bash
git push origin HEAD
git push origin vX.Y.Z
```

### 4. Verify

```bash
git tag -l 'v*'
cat VERSION
git show vX.Y.Z --no-patch
```

On GitHub, check that the **Publish Docker image** workflow ran for the new tag (Actions tab).

---

## Common commands

### First public release (tag current `VERSION` without bumping)

When `VERSION` already says `1.0.0` and you want tag `v1.0.0`:

```bash
./scripts/release.sh tag -m "First public release" --push
```

### Patch / minor / major release

```bash
./scripts/release.sh patch -m "Fix filter edge case" --push
./scripts/release.sh minor -m "Add schema export endpoint" --push
./scripts/release.sh major -m "Remove legacy /apis paths" --push
```

### Set an exact version

```bash
./scripts/release.sh 1.2.3 -m "Set version explicitly" --push
```

### Skip changelog update

```bash
./scripts/release.sh patch --skip-changelog --push
```

### Options reference

| Option | Effect |
|--------|--------|
| `--push` | Push current branch and new tag to `origin` |
| `--dry-run` | Show plan only; no file or git changes |
| `--allow-dirty` | Allow uncommitted changes (emergency use only) |
| `-m`, `--message` | Release note for `CHANGELOG.md` and the annotated tag |
| `--skip-changelog` | Update `VERSION` only; do not edit `CHANGELOG.md` |

---

## Docker image publish

Pushing a tag matching `v*.*.*` triggers [`.github/workflows/docker-publish.yml`](../.github/workflows/docker-publish.yml).

Images are published to **GitHub Container Registry**:

```text
ghcr.io/dbapiator/dbapi:latest
ghcr.io/dbapiator/dbapi:1.0.1
ghcr.io/dbapiator/dbapi:1.0
ghcr.io/dbapiator/dbapi:1
```

Pin production deployments to a specific version tag rather than `latest`.

The workflow also runs a Trivy container scan and uploads results to GitHub Security.

---

## GitHub Release (optional)

After pushing the tag, you can publish release notes on GitHub:

```bash
gh release create v1.0.1 \
  --title "dbAPI 1.0.1" \
  --notes-file - <<'EOF'
## Highlights

- Fix filter edge case
- Update Docker publish workflow

See [CHANGELOG.md](CHANGELOG.md) for full history.
EOF
```

Or create the release from the GitHub UI: **Releases → Draft a new release → Choose tag `vX.Y.Z`**.

---

## Changelog hygiene

Before running the script, you may add bullets under `## [Unreleased]` in `CHANGELOG.md`. The script prepends a new section with your `-m` message as the first bullet; edit the file afterward if you need more detail, then amend the release commit **only before pushing** (or include extra notes in a follow-up doc commit before tagging).

Recommended pattern:

```markdown
## [Unreleased]

- Added export for effective schema

## [1.0.1] - 2026-06-12
...
```

---

## Troubleshooting

### `working tree has uncommitted changes`

Commit or stash unrelated work, then re-run. Do not use `--allow-dirty` for routine releases.

### `tag vX.Y.Z already exists`

Tags are immutable. Bump to the next version (`patch` / `minor` / `major`) or delete the local tag only if you never pushed it:

```bash
git tag -d vX.Y.Z   # local only; avoid on shared/pushed tags
```

### Invalid contents in `VERSION`

If `VERSION` is corrupted, restore a single line with valid semver:

```text
1.0.1
```

The script ignores invalid `VERSION` contents and falls back to the latest git tag, but you should fix the file before the next release.

### Docker workflow did not run

- Confirm the tag was **pushed** to GitHub: `git ls-remote --tags origin`
- Tag must match `v*.*.*` (e.g. `v1.0.1`, not `1.0.1`)
- Check Actions permissions and package write access for the repository

---

## Checklist (copy before each release)

- [ ] All intended changes committed on the release branch
- [ ] Tests pass (`composer test:unit` at minimum)
- [ ] `./scripts/release.sh --dry-run …` looks correct
- [ ] `./scripts/release.sh … --push` completed
- [ ] Tag visible on GitHub; Docker workflow succeeded
- [ ] Optional: GitHub Release published
- [ ] Optional: announce / update deployment pins (`ghcr.io/...:X.Y.Z`)
