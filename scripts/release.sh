#!/usr/bin/env bash
#
# Create a new dbAPI release: bump VERSION, update CHANGELOG, commit, and tag.
#
# Usage:
#   ./scripts/release.sh tag              # tag current VERSION (e.g. first v1.0.0)
#   ./scripts/release.sh patch            # 1.0.0 -> 1.0.1
#   ./scripts/release.sh minor            # 1.0.0 -> 1.1.0
#   ./scripts/release.sh major            # 1.0.0 -> 2.0.0
#   ./scripts/release.sh 1.2.3            # set exact version, then tag
#
# Options:
#   --push          push commit and tag to origin after creating them
#   --dry-run       show planned actions without writing files or git changes
#   --allow-dirty   allow uncommitted changes besides VERSION/CHANGELOG
#   -m, --message   release notes (CHANGELOG bullet and annotated tag message)
#   --skip-changelog  only update VERSION; do not edit CHANGELOG.md
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="${ROOT}/VERSION"
CHANGELOG_FILE="${ROOT}/CHANGELOG.md"

BUMP=""
EXACT_VERSION=""
PUSH=false
DRY_RUN=false
ALLOW_DIRTY=false
SKIP_CHANGELOG=false
MESSAGE=""

usage() {
    sed -n '3,20p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

log() {
    echo "==> $*"
}

die() {
    echo "error: $*" >&2
    exit 1
}

semver_valid() {
    [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

read_version() {
    local version=""
    local from_file=""

    if [[ -f "$VERSION_FILE" ]]; then
        from_file="$(tr -d '[:space:]' < "$VERSION_FILE")"
        if semver_valid "$from_file"; then
            version="$from_file"
        fi
    fi

    if [[ -z "$version" ]]; then
        local tag
        tag="$(git -C "$ROOT" describe --tags --abbrev=0 2>/dev/null || true)"
        tag="${tag#v}"
        if semver_valid "$tag"; then
            version="$tag"
        fi
    fi

    if [[ -z "$version" ]]; then
        version="0.0.0"
    fi

    if [[ -n "$from_file" ]] && ! semver_valid "$from_file"; then
        echo "warning: ignoring invalid VERSION file contents: ${from_file}" >&2
    fi

    echo "$version"
}

bump_version() {
    local current="$1"
    local kind="$2"
    local major minor patch

    IFS='.' read -r major minor patch <<< "$current"

    case "$kind" in
        patch) patch=$((patch + 1)) ;;
        minor)
            minor=$((minor + 1))
            patch=0
            ;;
        major)
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        *)
            die "unknown bump kind: ${kind}"
            ;;
    esac

    echo "${major}.${minor}.${patch}"
}

update_changelog() {
    local version="$1"
    local date notes entry

    date="$(date +%Y-%m-%d)"
    notes="${MESSAGE:-Release v${version}.}"

    if [[ ! -f "$CHANGELOG_FILE" ]]; then
        cat > "$CHANGELOG_FILE" <<EOF
# Changelog

All notable changes to dbAPI are documented here. Version numbers follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

EOF
    fi

    entry="## [${version}] - ${date}

- ${notes}

"

    if grep -q '^## \[Unreleased\]' "$CHANGELOG_FILE"; then
        local tmp
        tmp="$(mktemp)"
        awk -v entry="$entry" '
            /^## \[Unreleased\]/ {
                print entry
            }
            { print }
        ' "$CHANGELOG_FILE" > "$tmp"
        mv "$tmp" "$CHANGELOG_FILE"
    else
        {
            head -n 5 "$CHANGELOG_FILE" 2>/dev/null || true
            printf '%s\n' "$entry"
            tail -n +6 "$CHANGELOG_FILE" 2>/dev/null || true
        } > "${CHANGELOG_FILE}.tmp"
        mv "${CHANGELOG_FILE}.tmp" "$CHANGELOG_FILE"
    fi
}

ensure_git_ready() {
    if ! git -C "$ROOT" rev-parse --git-dir >/dev/null 2>&1; then
        die "not a git repository: ${ROOT}"
    fi

    if [[ "$ALLOW_DIRTY" == false ]]; then
        if ! git -C "$ROOT" diff --quiet || ! git -C "$ROOT" diff --cached --quiet; then
            die "working tree has uncommitted changes; commit or stash first, or pass --allow-dirty"
        fi
    fi

    if git -C "$ROOT" rev-parse "v${NEW_VERSION}" >/dev/null 2>&1; then
        die "tag v${NEW_VERSION} already exists"
    fi
}

run() {
    if [[ "$DRY_RUN" == true ]]; then
        log "[dry-run] $*"
    else
        "$@"
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        -h|--help)
            usage 0
            ;;
        --push)
            PUSH=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --allow-dirty)
            ALLOW_DIRTY=true
            shift
            ;;
        --skip-changelog)
            SKIP_CHANGELOG=true
            shift
            ;;
        -m|--message)
            [[ $# -ge 2 ]] || die "missing value for $1"
            MESSAGE="$2"
            shift 2
            ;;
        tag)
            BUMP="tag"
            shift
            ;;
        patch|minor|major)
            BUMP="$1"
            shift
            ;;
        *)
            if semver_valid "$1"; then
                EXACT_VERSION="$1"
                shift
            else
                die "unknown argument: $1 (run with --help)"
            fi
            ;;
    esac
done

CURRENT_VERSION="$(read_version)"

if [[ -n "$EXACT_VERSION" ]]; then
    NEW_VERSION="$EXACT_VERSION"
elif [[ "$BUMP" == "tag" ]]; then
    NEW_VERSION="$CURRENT_VERSION"
elif [[ -n "$BUMP" ]]; then
    NEW_VERSION="$(bump_version "$CURRENT_VERSION" "$BUMP")"
else
    usage 1
fi

if ! semver_valid "$NEW_VERSION"; then
    die "invalid target version: ${NEW_VERSION}"
fi

TAG="v${NEW_VERSION}"
TAG_MESSAGE="${MESSAGE:-Release ${TAG}.}"

log "current version: ${CURRENT_VERSION}"
log "new version:     ${NEW_VERSION}"
log "tag:             ${TAG}"

ensure_git_ready

if [[ "$NEW_VERSION" != "$CURRENT_VERSION" ]]; then
    log "writing ${VERSION_FILE}"
    if [[ "$DRY_RUN" == true ]]; then
        log "[dry-run] write ${VERSION_FILE} -> ${NEW_VERSION}"
    else
        printf '%s\n' "$NEW_VERSION" > "$VERSION_FILE"
    fi
fi

if [[ "$SKIP_CHANGELOG" == false ]]; then
    log "updating ${CHANGELOG_FILE}"
    if [[ "$DRY_RUN" == true ]]; then
        log "[dry-run] prepend changelog entry for v${NEW_VERSION}"
    else
        update_changelog "$NEW_VERSION"
    fi
fi

if [[ "$DRY_RUN" == false ]]; then
    git -C "$ROOT" add "$VERSION_FILE"
    if [[ "$SKIP_CHANGELOG" == false ]]; then
        git -C "$ROOT" add "$CHANGELOG_FILE"
    fi

    if git -C "$ROOT" diff --cached --quiet; then
        log "VERSION and CHANGELOG unchanged; skipping commit"
    else
        log "committing release files"
        git -C "$ROOT" commit -m "Release ${TAG}"
    fi

    log "creating annotated tag ${TAG}"
    git -C "$ROOT" tag -a "$TAG" -m "$TAG_MESSAGE"
else
    log "[dry-run] would commit VERSION/CHANGELOG (if changed) and create tag ${TAG}"
fi

if [[ "$PUSH" == true ]]; then
    branch="$(git -C "$ROOT" rev-parse --abbrev-ref HEAD)"
    log "pushing ${branch} and ${TAG} to origin"
    run git -C "$ROOT" push origin "$branch"
    run git -C "$ROOT" push origin "$TAG"
fi

log "done: ${TAG}"
if [[ "$PUSH" == false && "$DRY_RUN" == false ]]; then
    echo
    echo "Next: git push origin HEAD && git push origin ${TAG}"
    echo "Or re-run with --push"
fi
