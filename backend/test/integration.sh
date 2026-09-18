#!/usr/bin/env bash
# Integration test for the PHP backend (uses curl against a live PHP server
# and MySQL/MariaDB). Run after creating the schema:
#   php backend/cli/create.php
#
# Usage:
#   BASE=http://127.0.0.1:3001 DB_HOST=127.0.0.1 DB_USER=root DB_PASSWORD=secret bash test/integration.sh
set -uo pipefail

BASE="${BASE:-http://127.0.0.1:3001}"
BACKEND_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_TAG="${TEST_TAG:-$$}"
COOKIES_ADMIN="$(mktemp)"
COOKIES_TEACHER="$(mktemp)"
COOKIES_ATTACKER="$(mktemp)"
TMP_XLSX="$(mktemp --suffix=.xlsx)"
PASS=0
FAIL=0

check() {
    local name="$1" actual="$2" expected="$3"
    if [[ "$actual" == "$expected" ]]; then
        PASS=$((PASS + 1)); echo "PASS $name"
    else
        FAIL=$((FAIL + 1)); echo "FAIL $name"
        echo "  expected: $expected"
        echo "  actual:   $actual"
    fi
}

# create a throwaway admin account directly (bcrypt cost 12)
php -r "
require '$BACKEND_DIR/src/helpers/helpers.php';
spl_autoload_register(function(\$c){ if(str_starts_with(\$c,'Cenusis\\\\')) require '$BACKEND_DIR/src/'.str_replace('\\\\','/',substr(\$c,8)).'.php'; });
\$pdo = Cenusis\Db\Db::pdo();
\$hash = password_hash('itestpass123', PASSWORD_BCRYPT, ['cost'=>12]);
\$stmt = \$pdo->prepare('INSERT INTO loggedin_users (username, normalized_username, password_hash, role) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)');
\$stmt->execute(['itestadmin', Cenusis\Helpers\\normalize_arabic('itestadmin'), \$hash, 'admin']);
echo 'seeded';
" || { echo "seed failed (set DB_* env)"; exit 1; }

# 1. discover
check "public discover" "$(curl -s $BASE/api/public/discover)" '["login","logout"]'

# 2. login returns role + user_id
check "login ok" "$(curl -s -c "$COOKIES_ADMIN" -X POST $BASE/api/public/call -H 'Content-Type: application/json' -d '{"method":"login","params":["itestadmin","itestpass123"]}' | cut -c1-16)" '{"success":true,'

# 3. wrong password
check "login wrong password" "$(curl -s -X POST $BASE/api/public/call -H 'Content-Type: application/json' -d '{"method":"login","params":["itestadmin","badpass123"]}')" '{"success":false,"error":"Unauthorized"}'

# 4. account info with cookie (id depends on previous rows)
check "getAccountInfo" "$(curl -s -b "$COOKIES_ADMIN" -X POST $BASE/api/admin/call -H 'Content-Type: application/json' -d '{"method":"getAccountInfo","params":[]}' | cut -c1-29)" '{"success":true,"data":{"id":'

# 5. admin call without cookie
check "unauthorized admin call" "$(curl -s -X POST $BASE/api/admin/call -H 'Content-Type: application/json' -d '{"method":"fetchTeachers","params":[]}')" '{"success":false,"error":"Authentication failed"}'

# 6. unknown function
check "unknown function" "$(curl -s -X POST $BASE/api/public/call -H 'Content-Type: application/json' -d '{"method":"nope","params":[]}')" '{"success":false,"error":"RPC function '\''nope'\'' not found"}'

# 7. empty body -> 400 Invalid JSON
check "empty body 400" "$(curl -s -w '|%{http_code}' -X POST $BASE/api/public/call -H 'Content-Type: application/json' -d '{}')" '{"error":"Invalid JSON"}|400'

# 8. register teacher + role separation
check "registerTeacher" "$(curl -s -b "$COOKIES_ADMIN" -X POST $BASE/api/admin/call -H 'Content-Type: application/json' -d '{"method":"registerTeacher","params":[{"teacher_name":"طالب اختبار '$TEST_TAG'","password":"teach12345"}]}' | cut -c1-16)" '{"success":true,'
    curl -s -c "$COOKIES_TEACHER" -X POST $BASE/api/public/call -H 'Content-Type: application/json' -d '{"method":"login","params":["طالب اختبار '$TEST_TAG'","teach12345"]}' > /dev/null
check "teacher token rejected on admin route" "$(curl -s -b "$COOKIES_TEACHER" -X POST $BASE/api/admin/call -H 'Content-Type: application/json' -d '{"method":"fetchTeachers","params":[]}')" '{"success":false,"error":"Authentication failed"}'
check "teacher route works" "$(curl -s -b "$COOKIES_TEACHER" -X POST $BASE/api/teacher/call -H 'Content-Type: application/json' -d '{"method":"getAccountInfo","params":[]}' | cut -c1-16)" '{"success":true,'

# 9. handler with real data (grading system CRUD)
check "newGradingSystem" "$(curl -s -b "$COOKIES_ADMIN" -X POST $BASE/api/admin/call -H 'Content-Type: application/json' -d '{"method":"newGradingSystem","params":[{"name":"نظام اختبار '$TEST_TAG'","fields":[{"field_name":"امتحان","min_grade":0,"max_grade":60}]}]}' | cut -c1-16)" '{"success":true,'

# 10. IDOR: a subject owned by the registered teacher; a second (attacker)
# teacher must not be able to import grades into it or read its enrollments.
check "newSubject owned by teacher" "$(curl -s -b "$COOKIES_ADMIN" -X POST $BASE/api/admin/call -H 'Content-Type: application/json' -d '{"method":"newSubject","params":[{"subject_name":"مادة اختبار '$TEST_TAG'","teacher_name":"طالب اختبار '$TEST_TAG'","degree":"بكلوريوس","class":3,"hours_weekly":10,"semester":1,"grading_system_name":"نظام اختبار '$TEST_TAG'"}]}' | cut -c1-16)" '{"success":true,'

# second teacher (owns nothing)
curl -s -b "$COOKIES_ADMIN" -X POST $BASE/api/admin/call -H 'Content-Type: application/json' -d '{"method":"registerTeacher","params":[{"teacher_name":"مهاجم اختبار '$TEST_TAG'","password":"hack123456"}]}' > /dev/null
curl -s -c "$COOKIES_ATTACKER" -X POST $BASE/api/public/call -H 'Content-Type: application/json' -d '{"method":"login","params":["مهاجم اختبار '$TEST_TAG'","hack123456"]}' > /dev/null

# attacker's own subject (teachers create subjects via their own route)
check "attacker newSubject (own subject)" "$(curl -s -b "$COOKIES_ATTACKER" -X POST $BASE/api/teacher/call -H 'Content-Type: application/json' -d '{"method":"newSubject","params":[{"subject_name":"مادة المهاجم '$TEST_TAG'","teacher_name":"مهاجم اختبار '$TEST_TAG'","degree":"بكلوريوس","class":4,"hours_weekly":10,"semester":1,"grading_system_name":"نظام اختبار '$TEST_TAG'"}]}' | cut -c1-16)" '{"success":true,'

# findSubjectByName expects the pre-normalized name (أ->ا, ة->ه)
SUBJECT_ID=$(curl -s -b "$COOKIES_ATTACKER" -X POST $BASE/api/teacher/call -H 'Content-Type: application/json' -d '{"method":"findSubjectByName","params":["ماده اختبار '$TEST_TAG'"]}' | python3 -c 'import json,sys; print(json.load(sys.stdin).get("data",{}).get("id",""))')
ATTACKER_SUBJECT_ID=$(curl -s -b "$COOKIES_ATTACKER" -X POST $BASE/api/teacher/call -H 'Content-Type: application/json' -d '{"method":"findSubjectByName","params":["ماده المهاجم '$TEST_TAG'"]}' | python3 -c 'import json,sys; print(json.load(sys.stdin).get("data",{}).get("id",""))')

# attacker uploads grades into the teacher's subject -> 401 (IDOR guard)
printf 'placeholder' > "$TMP_XLSX"
check "IDOR: attacker grades import on foreign subject" "$(curl -s -w '|%{http_code}' -b "$COOKIES_ATTACKER" -F 'file=@'"$TMP_XLSX" $BASE/api/grades/import/$SUBJECT_ID)" '{"success":false,"error":"unauthorized"}|401'
check "IDOR: attacker lab import on foreign subject" "$(curl -s -w '|%{http_code}' -b "$COOKIES_ATTACKER" -F 'file=@'"$TMP_XLSX" $BASE/api/lab/grades/import/$SUBJECT_ID)" '{"success":false,"error":"unauthorized"}|401'
check "IDOR: attacker grades template on foreign subject" "$(curl -s -w '|%{http_code}' -b "$COOKIES_ATTACKER" $BASE/api/grades/template/$SUBJECT_ID)" '{"success":false,"error":"unauthorized"}|401'
check "IDOR: attacker fetchEnrollmentsForSubject denied" "$(curl -s -b "$COOKIES_ATTACKER" -X POST $BASE/api/teacher/call -H 'Content-Type: application/json' -d '{"method":"fetchEnrollmentsForSubject","params":['"$SUBJECT_ID"']}')" '{"success":false,"error":"\u0644\u064a\u0633 \u0644\u062f\u064a\u0643 \u0635\u0644\u0627\u062d\u064a\u0629 \u0639\u0644\u0649 \u0647\u0630\u0647 \u0627\u0644\u0645\u0627\u062f\u0629"}'

# owner teacher is allowed on their own subject (template download works)
check "IDOR: owner grades template allowed" "$(curl -s -b "$COOKIES_ATTACKER" -o /dev/null -w '%{http_code}' $BASE/api/grades/template/$ATTACKER_SUBJECT_ID)" '200'

echo
echo "passed: $PASS, failed: $FAIL"
rm -f "$COOKIES_ADMIN" "$COOKIES_TEACHER" "$COOKIES_ATTACKER" "$TMP_XLSX"
exit $((FAIL > 0 ? 1 : 0))
