# Developer Challenge - Invoicing (PHP + MySQL)

This project implements a simple web app to capture invoices and store them in a MySQL database.

It is intentionally designed as a module that could live inside a bigger system.

---

## Tech Stack

- PHP 8.0+
- MySQL
- HTML + JavaScript + CSS

---

## Features Implemented

### UI (public/index.php)
- Capture invoice header:
  - Company
  - Client
  - Invoice number
  - Invoice date + due date
  - Tax rate (optional)
  - Notes / comments
- Capture invoice items:
  - Description
  - Quantity
  - Unit
  - Unit price
  - **Per-line "taxed" checkbox**
  - Line totals (calculated live)
- Totals calculated client-side for immediate feedback:
  - Subtotal
  - Taxable amount
  - Tax
  - Total

### API (public/save_invoice.php)
- Accepts invoice data via POST
- Validates input server-side
- Calculates totals server-side (does not trust client totals)
- Store invoice + items

---

## Database Schema

The schema is designed to model invoices in a real system:

- `companies` - issuing entity (pre-configured/selectable)
- `clients` - bill-to client (pre-configured/selectable)
- `tax_rates` - selectable tax rate (pre-configured/selectable)
- `invoices` - invoice header (totals stored)
- `invoice_items` - invoice line items (each line has its own `taxed` flag)

> Seed data is included in `sql/schema.sql` so the app works immediately after import.

---

## Setup Instructions

### 1) Create the database + tables

From your MySQL client / terminal:

```bash
mysql -u root -p < sql/schema.sql
```

---

### 2) Configure environment variables

Configure the `.env` file in project root:

```env
APP_DEBUG=true
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=invoicing
```

---

### 3) Run the app

Run the project using your usual PHP setup (Apache/Nginx + PHP-FPM, XAMPP, etc.) and point your web server document root to the `public/` directory.

Document root:
- `/path/to/invoicing/public`

Then open the configured URL in your browser (e.g. your local vhost domain).

---
