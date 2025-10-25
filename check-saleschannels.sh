#!/usr/bin/env bash
# -------------------------------------------------------------------
#  Shopware API Quick Check + Schreibrechte-Test
#  - Holt automatisch Access Token für Integration
#  - Listet alle Sales-Channels mit ihrer Root-Kategorie
#  - Legt eine Testkategorie an und löscht sie wieder
# -------------------------------------------------------------------

# === Konfiguration ===
SHOPWARE_URL="https://test-sw6.ddev.site"
CLIENT_ID="SWIAOW1IBFHHSVHHDZQ1VLB1MQ"
CLIENT_SECRET="SGVTTUF2V0I2bXNaWWlqTEJWWjRyWEhGWUo1c2NsRTdhNFNZaHY"

# === Access Token holen ===
echo "🔐 Hole Access Token von ${SHOPWARE_URL} ..."
TOKEN_RESPONSE=$(curl -s -X POST "${SHOPWARE_URL}/api/oauth/token" \
  -H "Content-Type: application/json" \
  -d "{
        \"grant_type\": \"client_credentials\",
        \"client_id\": \"${CLIENT_ID}\",
        \"client_secret\": \"${CLIENT_SECRET}\"
      }")

ACCESS_TOKEN=$(echo "$TOKEN_RESPONSE" | grep -oP '(?<=\"access_token\":\")[^\"]*')

if [ -z "$ACCESS_TOKEN" ]; then
  echo "❌ Fehler: Konnte keinen Access Token abrufen!"
  echo "Antwort: $TOKEN_RESPONSE"
  exit 1
fi

echo "✅ Access Token erfolgreich geholt."
echo

# === Sales-Channels abrufen ===
echo "🏪 Lade Sales-Channels ..."
CHANNELS=$(curl -s -X GET "${SHOPWARE_URL}/api/sales-channel" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -H "Accept: application/json")

# === Ausgabe formatieren ===
echo
echo "📋 Gefundene Sales-Channels:"
echo "----------------------------------------------------------"

echo "$CHANNELS" | jq -r '
  .data[]? | [
    .attributes.name,
    .attributes.typeId,
    .relationships.navigationCategory.data.id
  ] | @tsv
' | awk -F'\t' '{printf "• %s (%s)\n  Root-Kategorie-ID: %s\n\n", $1, $2, $3}'

# === Testkategorie anlegen ===
echo "🧪 Teste Schreibrechte: Erstelle temporäre Kategorie..."
CREATE_RESPONSE=$(curl -s -X POST "${SHOPWARE_URL}/api/category" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{
        \"name\": \"_TestMigrationKategorie\",
        \"active\": true
      }")

CATEGORY_ID=$(echo "$CREATE_RESPONSE" | grep -oP '(?<=\"id\":\")[^\"]*')

if [ -z "$CATEGORY_ID" ]; then
  echo "⚠️  Konnte keine Testkategorie anlegen!"
  echo "Antwort: $CREATE_RESPONSE"
else
  echo "✅ Testkategorie erfolgreich angelegt (ID: $CATEGORY_ID)"

  # Testkategorie wieder löschen
  echo "🧹 Lösche Testkategorie wieder..."
  DELETE_RESPONSE=$(curl -s -X DELETE "${SHOPWARE_URL}/api/category/${CATEGORY_ID}" \
    -H "Authorization: Bearer ${ACCESS_TOKEN}")

  if [ -z "$DELETE_RESPONSE" ]; then
    echo "✅ Testkategorie erfolgreich gelöscht."
  else
    echo "⚠️  Antwort beim Löschen: $DELETE_RESPONSE"
  fi
fi

echo
echo "🏁 Test abgeschlossen."

