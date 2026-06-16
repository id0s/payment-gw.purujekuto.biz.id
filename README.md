# 💳 RFID Closed-Loop Payment Gateway & Point of Sales (POS)

A lightweight, secure, and modern self-hosted closed-loop payment gateway and point-of-sale integration for RFID systems. Features a premium glassmorphic dark theme dashboard, automated online payments, manual admin management, hardware reader integration, and an optional enterprise-grade multi-tenant SaaS architecture.

---

## ✨ Features

- 💎 **Premium Glassmorphic Dark UI**: Modern dark theme dashboard with custom radial-gradient animations, responsive layout, dynamic counters, and micro-interactions.
- 🎨 **Dynamic Branding & White-Labeling**: Customize brand accents and gradients directly from the dashboard settings panel.
- 💰 **Hybrid Top-Up System**:
  - **Online Gateway**: Request dynamic QRIS, Virtual Accounts (BCA, Mandiri, BNI, BSI, etc.), or Retail payment links (Alfamart/Indomaret) powered by **WijayaPay API**.
  - **Manual Administrator Top-up**: Instant balance credit with customized description logs (just restored!).
- 🤖 **Hardware Integration Ready**: Standardized endpoints for ESP32 or USB RFID card readers (potong saldo / check balance) secured by API token authentication.
- 🔒 **Enterprise-Grade Security**: Row-level database transactions, SQL injection protection, MD5 signature checks, and transaction auditing logs.
- 🏬 **Point of Sales (POS) Client**: Connected local shop interface to manage products, scan cards for payment, check stock levels, and print transactions.

---

## 🏛️ Deployment Architectures

You can configure this gateway in one of two configurations:

### 1. Single-Merchant Setup
Perfect for single stores, schools, canteens, or closed-loop environments.
- **SQL Script**: `install.sql`
- **Key Tables**: `users`, `transactions`, `payment_methods`, `topup_requests`, `devices`, `rfid_logs`, `admins`, `settings`, `suppliers`, `products`, `purchase_orders`, `purchase_order_details`, `transaksi`, `transaksi_detail`.

### 2. Multi-Tenant SaaS Setup
Allows hosting a commercial platform where multiple independent businesses register and operate their own isolated RFID networks.
- **SQL Script**: `install_saas.sql`
- **Key Partitioning**: Logic isolated by `tenant_id` on all store-related tables.
- **Unique Multi-Tenant Constraints**: Allows registering the same physical card UID (e.g. `A1B2C3D4`) on different merchant systems with isolated balances.
- **Documentation**: See `SAAS_GUIDE.md` for architectural details and subdomain routing.

---

## 🚀 Installation & Setup

### 1. Prerequisites
- PHP 8.0+ (with `PDO` and `cURL` enabled).
- MySQL / MariaDB Server.
- Web Server (Apache/Nginx) with rewrite rules enabled (`.htaccess` included).

### 2. Database Import
Create a database named `rfid_payment` and import the schema script:
```bash
# For Single-Merchant:
mysql -u username -p rfid_payment < install.sql

# For SaaS Platform:
mysql -u username -p rfid_payment < install_saas.sql
```

### 3. Application Configuration
Rename/configure values in `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfid_payment');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('API_TOKEN', 'your-custom-hardware-token');
define('WIJAYAPAY_MERCHANT_CODE', 'your-merchant-code');
define('WIJAYAPAY_API_KEY', 'your-api-key');
```

---

## 📡 Hardware RFID Reader API (ESP32 / NodeMCU)

Secure your physical readers using the `X-Api-Token` header or `?token=` parameter.

### 1. Tap to Pay / Deduct Balance
Send a request when a card is tapped on a scanner:
```http
GET /api/rfid.php?action=tap&uid=A1B2C3D4&device=DEV-001&token=your-token
```
**Response (Success)**:
```json
{
  "status": "success",
  "message": "Pembayaran berhasil",
  "nama": "Budi Santoso",
  "jumlah": 10000,
  "sisa_saldo": 40000,
  "order_id": "TAP-20260616-XXXX"
}
```

### 2. Check Card Status & Balance
```http
GET /api/rfid.php?action=check&uid=A1B2C3D4&token=your-token
```

---

## 📦 Database Table Map

| Table | Purpose | Project Scope |
| :--- | :--- | :--- |
| `users` | Holds RFID card holders, active statuses, and account balance. | Gateway Core |
| `transactions` | Auditable log of all platform topups and merchant payments. | Gateway Core |
| `payment_methods` | Virtual Accounts and e-wallet configurations for transfer. | Gateway Core |
| `topup_requests` | Stores customer transfer receipts waiting for admin approval. | Gateway Core |
| `devices` | Registered physical reader terminals (with tap price limits). | Gateway Core |
| `rfid_logs` | Hardware tap log history for debugging and telemetry. | Gateway Core |
| `admins` | Platform / Tenant login credential accounts (Superadmin/Admin/Operator roles). | Gateway Core |
| `settings` | Dynamic key-value store configurations (branding, limits). | Gateway Core |
| `suppliers` | Direct wholesale inventory providers list. | POS Client / Supply Chain |
| `products` | Point-Of-Sale inventory items (SKU, stock, cost price, retail price). | POS Client / Inventory |
| `purchase_orders` | Wholesale incoming restock batch order headers. | POS Client / Supply Chain |
| `purchase_order_details` | Quantities, items, and cost rates of specific restock orders. | POS Client / Supply Chain |
| `transaksi` | Store orders metadata (payment methods, RFID UID, totals). | POS Client |
| `transaksi_detail` | Items inside each POS shopping cart invoice. | POS Client |

---

## 📄 License
This project is open-source and licensed under the MIT License.
