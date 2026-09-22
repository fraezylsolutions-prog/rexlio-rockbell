#!/bin/bash
# Rexlio hybrid sync - the part that runs ON THE ONLINE HOST (over SSH), or locally
# in test mode. Called by tools/sync_to_online.php; never run it by hand on live.
#
#   rexlio_sync_remote.sh <work dir> <database> <upload.sql> <expected_counts.txt> [keep]
#
# Order, and what it prints (one line per step, machine-readable):
#   1. backup the online database   -> STEP backup OK file=... bytes=...
#   2. restore the uploaded dump     -> STEP restore OK
#   3. compare row counts            -> STEP verify OK tables=N
#   RESULT DONE
# Any failure in 2 or 3 puts the backup from 1 straight back:
#   STEP rollback OK  + RESULT ROLLED_BACK      (online copy exactly as before)
#   STEP rollback FAILED + RESULT FAILED ...     (needs a human; the backup file is named)
# The database password never appears here: mysql/mysqldump read it from
# <work dir>/online.cnf, a [client] option file the owner creates once.
set -u
dir="$1"; db="$2"; up="$3"; expected="$4"; keep="${5:-10}"
MYSQL="${MYSQL_BIN:-mysql}"; DUMP="${MYSQLDUMP_BIN:-mysqldump}"
cnf="$dir/online.cnf"
err="$dir/last_error.txt"

fail() { echo "RESULT FAILED $1"; exit "${2:-1}"; }
[ -d "$dir" ] || fail "reason=work_dir_missing dir=$dir" 2
[ -r "$cnf" ] || fail "reason=missing_online.cnf path=$cnf" 2
[ -s "$up" ] || fail "reason=upload_missing_or_empty file=$up" 2
[ -s "$expected" ] || fail "reason=expected_counts_missing file=$expected" 2
"$MYSQL" --defaults-extra-file="$cnf" -N -e "SELECT 1" "$db" >/dev/null 2>"$err" || fail "reason=cannot_connect detail=$(head -c 200 "$err" | tr '\n' ' ')" 2
echo "STEP connect OK db=$db"

# 1. backup ------------------------------------------------------------------
ts=$(date +%Y%m%d_%H%M%S)
bak="$dir/online_before_sync_$ts.sql"
if ! "$DUMP" --defaults-extra-file="$cnf" --single-transaction --skip-lock-tables --add-drop-table --routines --triggers --default-character-set=utf8mb4 "$db" > "$bak" 2>"$err"; then
    echo "STEP backup FAILED detail=$(head -c 300 "$err" | tr '\n' ' ')"
    fail "stage=backup" 3
fi
if ! tail -c 200 "$bak" | grep -q "Dump completed"; then
    echo "STEP backup FAILED detail=dump_file_incomplete"
    fail "stage=backup" 3
fi
bytes=$(wc -c < "$bak" | tr -d ' ')
echo "STEP backup OK file=$(basename "$bak") bytes=$bytes"

# 2. restore -----------------------------------------------------------------
rollback() {
    if "$MYSQL" --defaults-extra-file="$cnf" "$db" < "$bak" 2>"$err"; then
        echo "STEP rollback OK file=$(basename "$bak")"
        echo "RESULT ROLLED_BACK $1"
        exit 4
    else
        echo "STEP rollback FAILED detail=$(head -c 300 "$err" | tr '\n' ' ')"
        fail "stage=rollback backup=$bak $1" 5
    fi
}
if "$MYSQL" --defaults-extra-file="$cnf" "$db" < "$up" 2>"$err"; then
    echo "STEP restore OK"
else
    echo "STEP restore FAILED detail=$(head -c 300 "$err" | tr '\n' ' ')"
    rollback "stage=restore"
fi

# 3. verify: exact row counts vs what the local side counted --------------------
mismatch=0; checked=0
while read -r t expect; do
    [ -z "$t" ] && continue
    have=$("$MYSQL" --defaults-extra-file="$cnf" -N -e "SELECT COUNT(*) FROM \`$t\`" "$db" 2>>"$err" || echo "ERR")
    checked=$((checked+1))
    if [ "$have" != "$expect" ]; then
        echo "STEP verify MISMATCH table=$t local=$expect online=$have"
        mismatch=$((mismatch+1))
    fi
done < "$expected"
if [ "$mismatch" -ne 0 ]; then
    echo "STEP verify FAILED mismatches=$mismatch"
    rollback "stage=verify mismatches=$mismatch"
fi
echo "STEP verify OK tables=$checked"

# housekeeping: keep the last N backups, drop the upload -------------------------
ls -1t "$dir"/online_before_sync_*.sql 2>/dev/null | tail -n +$((keep+1)) | while read -r old; do rm -f "$old"; done
rm -f "$up" "$expected"
echo "$ts $(basename "$bak") tables=$checked" >> "$dir/sync_history.txt"
echo "RESULT DONE backup=$(basename "$bak") tables=$checked"
exit 0
