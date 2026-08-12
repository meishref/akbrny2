cd /opt/telegram-student-bot

TOKEN='YOUR_WHATSAPP_TOKEN_HERE'
APP_ID="901707712486067"

echo "=== STEP 1 ==="

RESP1=$(curl -s \
"https://graph.facebook.com/v21.0/1242587097412738/subscribed_apps" \
-H "Authorization: Bearer EAAM0GSZCk2rMBSGUF8Yy72OML3kZC4j9MENZC8PjzEbKG4ZA4B9RVLspIZCgRsjWfC9r6vWkmd0lLLItXlsJqki2r6qJZBeZA6GGaa2KWpG1fQDXhUx3jYrdQqgxHDAZARrAxB3FpNEKZCK5Q9fkZAQZBlwxZBSrO7uluZBJh3AdhA2UBh9VTxNZB7mtZBQCCPyxRZArsEQNNgZDZD")

echo "$RESP1"

echo "$RESP1" | grep -q "$APP_ID" \
&& echo "APP_SUBSCRIBED: YES" \
|| echo "APP_SUBSCRIBED: NO"

if ! echo "$RESP1" | grep -q "$APP_ID"; then

    echo "=== STEP 2 POST ==="

    curl -s -X POST \
    "https://graph.facebook.com/v21.0/1242587097412738/subscribed_apps" \
    -H "Authorization: Bearer EAAM0GSZCk2rMBSGUF8Yy72OML3kZC4j9MENZC8PjzEbKG4ZA4B9RVLspIZCgRsjWfC9r6vWkmd0lLLItXlsJqki2r6qJZBeZA6GGaa2KWpG1fQDXhUx3jYrdQqgxHDAZARrAxB3FpNEKZCK5Q9fkZAQZBlwxZBSrO7uluZBJh3AdhA2UBh9VTxNZB7mtZBQCCPyxRZArsEQNNgZDZD"

    echo ""

    echo "=== STEP 2 VERIFY ==="

    RESP2=$(curl -s \
    "https://graph.facebook.com/v21.0/1242587097412738/subscribed_apps" \
    -H "Authorization: Bearer EAAM0GSZCk2rMBSGUF8Yy72OML3kZC4j9MENZC8PjzEbKG4ZA4B9RVLspIZCgRsjWfC9r6vWkmd0lLLItXlsJqki2r6qJZBeZA6GGaa2KWpG1fQDXhUx3jYrdQqgxHDAZARrAxB3FpNEKZCK5Q9fkZAQZBlwxZBSrO7uluZBJh3AdhA2UBh9VTxNZB7mtZBQCCPyxRZArsEQNNgZDZD")

    echo "$RESP2"

    echo "$RESP2" | grep -q "$APP_ID" \
    && echo "APP_SUBSCRIBED_AFTER_POST: YES" \
    || echo "APP_SUBSCRIBED_AFTER_POST: NO"

else

    echo "=== STEP 2: SKIPPED ==="

fi

echo "=== STEP 3 ==="

curl -s \
"https://graph.facebook.com/v21.0/735315316312322?fields=platform_type,code_verification_status,throughput" \
-H "Authorization: Bearer EAAM0GSZCk2rMBSGUF8Yy72OML3kZC4j9MENZC8PjzEbKG4ZA4B9RVLspIZCgRsjWfC9r6vWkmd0lLLItXlsJqki2r6qJZBeZA6GGaa2KWpG1fQDXhUx3jYrdQqgxHDAZARrAxB3FpNEKZCK5Q9fkZAQZBlwxZBSrO7uluZBJh3AdhA2UBh9VTxNZB7mtZBQCCPyxRZArsEQNNgZDZD"

echo ""

echo "=== STEP 4 ==="

grep -q '^WHATSAPP_APP_SECRET=.' .env \
&& echo "APP_SECRET_EXISTS" \
|| echo "APP_SECRET_MISSING"

echo "=== STEP 5 ==="

curl -s -o /dev/null \
-w "local POST: %{http_code}\n" \
-X POST \
"http://127.0.0.1:3847/webhook/whatsapp" \
-H "Content-Type: application/json" \
-d '{}'

echo "=== STEP 6 ==="

grep "POST /webhook/whatsapp" \
/var/log/nginx/access.log | tail -20 \
|| echo "(no POST matches)"

echo "=== STEP 7 ==="

pm2 logs telegram-bot --lines 100 --nostream \
| grep -E "WHATSAPP|WHATSAPP_STATUS|whatsappWebhook" \
|| echo "(no matches)"

echo "=== DONE ==="