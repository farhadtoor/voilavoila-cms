#!/usr/bin/env bash
set -euo pipefail

repo_root=$(git rev-parse --show-toplevel)
cd "$repo_root"

git config --local core.hooksPath .githooks
git config --local commit.template .gitmessage

git config --local --unset-all ai.coauthor >/dev/null 2>&1 || true
git config --local --add ai.coauthor "codex <codex@openai.com>"
git config --local --add ai.coauthor "Claude <noreply@anthropic.com>"

echo "Configured repository-local co-author attribution."
echo "Current co-authors:"
git config --local --get-all ai.coauthor
