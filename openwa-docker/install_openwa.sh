#!/usr/bin/env bash
# ==============================================================================
# Loewix Sales CRM — 1-Klik Installer OpenWA Self-Hosted (Baileys + Anti-Ban)
# Jalankan di server VPS aaPanel:
#   sudo bash /www/wwwroot/sales.id-giti.com/openwa-docker/install_openwa.sh
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SALES_V2_DIR="/www/wwwroot/sales-v2"

echo "================================================================"
echo "  Loewix OpenWA Self-Hosted Installer (Engine: Baileys Anti-Ban)"
echo "================================================================"

# 1. Pastikan Docker tersedia
if ! command -v docker >/dev/null 2>&1; then
  echo "[ERROR] Docker belum terinstall. Silakan install Docker dari menu aaPanel -> Docker terlebih dahulu."
  exit 1
fi

if docker compose version >/dev/null 2>&1; then
  COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE_CMD="docker-compose"
else
  echo "[ERROR] Docker Compose tidak ditemukan."
  exit 1
fi

# 2. Pull image terbaru & jalankan container
echo "[1/4] Menarik image OpenWA terbaru & menjalankan container..."
cd "$SCRIPT_DIR"
$COMPOSE_CMD pull
$COMPOSE_CMD up -d

# 3. Tunggu OpenWA siap (maks 45 detik)
echo "[2/4] Menunggu OpenWA API siap di http://127.0.0.1:2785 ..."
READY=0
for i in $(seq 1 45); do
  if curl -sf http://127.0.0.1:2785/api/health >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 1
done

if [ "$READY" -ne 1 ]; then
  echo "[WARNING] OpenWA butuh waktu lebih lama untuk start. Cek log dengan: docker logs --tail 50 openwa-api"
  exit 1
fi

echo "[OK] OpenWA API sudah ONLINE!"

# 4. Ambil Master API Key otomatis dari volume container
echo "[3/4] Membaca Master API Key OpenWA..."
API_KEY=""
for i in $(seq 1 10); do
  API_KEY=$(docker exec openwa-api cat /app/data/.api-key 2>/dev/null | tr -d '\r\n' || true)
  if [ -n "$API_KEY" ]; then
    break
  fi
  sleep 1
done

if [ -z "$API_KEY" ]; then
  echo "[WARNING] Gagal membaca /app/data/.api-key otomatis. Cek log dengan: docker logs openwa-api | grep -i 'api key'"
  exit 1
fi

echo "[OK] API Key ditemukan: ${API_KEY:0:14}********"

# 5. Otomatis tulis ke wa_gateway_config.json agar CRM langsung terhubung!
echo "[4/4] Menyinkronkan konfigurasi OpenWA ke CRM..."
update_config_json() {
  local TARGET_JSON="$1"
  local TARGET_DIR
  TARGET_DIR="$(dirname "$TARGET_JSON")"
  if [ -d "$TARGET_DIR" ]; then
    php -r '
      $file = $argv[1];
      $key  = $argv[2];
      $cfg  = file_exists($file) ? json_decode(@file_get_contents($file), true) : [];
      if (!is_array($cfg)) $cfg = [];
      $cfg["openwa_base_url"] = "http://127.0.0.1:2785";
      $cfg["openwa_api_key"]  = $key;
      $cfg["openwa_updated_at"] = date("Y-m-d H:i:s");
      file_put_contents($file, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      @chmod($file, 0664);
    ' "$TARGET_JSON" "$API_KEY"
    chown www:www "$TARGET_JSON" 2>/dev/null || true
    echo "   -> Tersimpan di $TARGET_JSON"
  fi
}

update_config_json "$PROJECT_DIR/includes/wa_gateway_config.json"
if [ -d "$SALES_V2_DIR/includes" ]; then
  update_config_json "$SALES_V2_DIR/includes/wa_gateway_config.json"
fi

echo ""
echo "================================================================"
echo "  SELESAI! OpenWA Siap Dipakai di CRM sales.id-giti.com"
echo "================================================================"
echo "  URL OpenWA : http://127.0.0.1:2785"
echo "  API Key    : $API_KEY"
echo ""
echo "  Langkah selanjutnya:"
echo "  1. Buka menu 'Pesan Masuk WA' (wa_support_settings.php) di CRM."
echo "  2. Klik 'Setup Cepat 4 Nomor Sales Loewix'."
echo "  3. Klik tombol 'Scan QR' di tiap nomor sales & scan lewat HP WA."
echo "================================================================"
