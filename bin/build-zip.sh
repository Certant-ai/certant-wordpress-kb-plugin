#!/usr/bin/env bash
# Builds certant-knowledgebase.zip: the folder WordPress installs from
# Plugins > Add New > Upload Plugin. Only the files the plugin needs go in.
#
# Usage: bin/build-zip.sh [output dir, default dist]   (prints the zip's path)
set -euo pipefail

cd "$(dirname "$0")/.."
out="${1:-dist}"

rm -rf "$out/certant-knowledgebase" "$out/certant-knowledgebase.zip"
mkdir -p "$out/certant-knowledgebase"
cp certant-knowledgebase.php readme.txt LICENSE "$out/certant-knowledgebase/"
(cd "$out" && zip -qrX certant-knowledgebase.zip certant-knowledgebase)
rm -rf "$out/certant-knowledgebase"

echo "$out/certant-knowledgebase.zip"
