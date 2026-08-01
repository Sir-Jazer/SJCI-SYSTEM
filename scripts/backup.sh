#!/usr/bin/env bash
#
# SJCI SYSTEM — nightly backup.
#
# Backs up BOTH pieces of state that matter:
#   1. the SQLite database  (all financial records, users, audit trail)
#   2. storage/app/public   (uploaded proof images / receipts)
#
# Writes a timestamped .tar.gz locally (with rotation) and, if the OCI CLI is
# configured, uploads it to an Oracle Object Storage bucket (off the server).
#
# Run it from cron (see DEPLOYMENT.md). Safe to run by hand any time.
#
set -euo pipefail

# --- Settings (edit these two for your server) -------------------------------
APP_DIR="/var/www/sjci"                 # where the app is deployed
BUCKET="sjci-backups"                   # Oracle Object Storage bucket name ("" to skip upload)

# Local retention: how many nightly archives to keep on the server.
KEEP=14

# -----------------------------------------------------------------------------
BACKUP_DIR="${APP_DIR}/storage/backups"
DB_FILE="${APP_DIR}/database/database.sqlite"
UPLOADS_DIR="${APP_DIR}/storage/app/public"
STAMP="$(date +%Y-%m-%d_%H%M%S)"
WORK="$(mktemp -d)"
ARCHIVE="${BACKUP_DIR}/sjci-backup-${STAMP}.tar.gz"

mkdir -p "${BACKUP_DIR}"

# 1. Consistent SQLite snapshot (safe even if someone is using the app).
#    `.backup` copies the DB without risking a torn read mid-write.
sqlite3 "${DB_FILE}" ".backup '${WORK}/database.sqlite'"

# 2. Uploaded files (may be absent if nothing has been uploaded yet).
if [ -d "${UPLOADS_DIR}" ]; then
  cp -a "${UPLOADS_DIR}" "${WORK}/public-uploads"
else
  mkdir -p "${WORK}/public-uploads"
fi

# 3. Bundle into one archive.
tar -czf "${ARCHIVE}" -C "${WORK}" database.sqlite public-uploads
rm -rf "${WORK}"
echo "Created ${ARCHIVE}"

# 4. Rotate local copies — keep the newest ${KEEP}.
ls -1t "${BACKUP_DIR}"/sjci-backup-*.tar.gz 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -f

# 5. Off-box copy to Oracle Object Storage, if the OCI CLI is available/configured.
if [ -n "${BUCKET}" ] && command -v oci >/dev/null 2>&1; then
  if oci os object put --bucket-name "${BUCKET}" --file "${ARCHIVE}" \
        --name "$(basename "${ARCHIVE}")" --force >/dev/null 2>&1; then
    echo "Uploaded to Object Storage bucket '${BUCKET}'"
  else
    echo "WARNING: Object Storage upload failed — local copy kept at ${ARCHIVE}" >&2
  fi
else
  echo "NOTE: OCI CLI not configured — local copy only (set up per DEPLOYMENT.md for off-box backups)"
fi