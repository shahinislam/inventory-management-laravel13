# Swift Inventory - Project Plan
> Modern Inventory & POS Management System
> Built with Laravel 13, Livewire 3, Tailwind CSS, Redis, Docker (Sail)

---

## 🧰 Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 |
| Frontend | Livewire 4 + Alpine.js |
| UI Library | Flux UI v2 (built-in) |
| Styling | Tailwind CSS |
| Auth | Laravel Fortify (built-in) |
| Roles | Manual Middleware (enum: admin, manager, staff, viewer) |
| Database | MySQL 8.4 |
| Cache/Queue | Redis (phpredis built-in) |
| Email Testing | Mailpit |
| Docker | Laravel Sail |
| Image Processing | Intervention Image v4 (Imagick driver) |
| Media Manager | Custom (WordPress-like, drag & drop) |
| PDF | barryvdh/laravel-dompdf |
| Thermal Print | mike42/escpos-php (optional, later) |
| Testing | PestPHP (built-in) |
| Code Style | Laravel Pint (built-in) |

---

## 🎨 Design System

- **Theme:** Dark/Light mode toggle
- **Style:** Glassmorphism UI cards
- **Navigation:** Collapsible sidebar
- **Philosophy:** Keyboard-first, less mouse work
- **Colors:** Deep navy + Electric blue accents

### Keyboard Shortcuts
| Key | Action |
|-----|--------|
| `F2` | New Sale |
| `F3` | Search Product |
| `F4` | New Purchase |
| `F5` | Stock Check |
| `Ctrl+P` | Print |
| `Ctrl+S` | Save |
| `ESC` | Cancel/Close |
| `Tab` | Next Field |

---

## 📦 Package Installation

```bash
# Install required packages
./vendor/bin/sail composer require intervention/image barryvdh/laravel-dompdf

# Optional (POS thermal printing - add later)
./vendor/bin/sail composer require mike42/escpos-php
```

### Add Imagick to Sail Dockerfile:
```bash
# Publish Sail Dockerfile first
./vendor/bin/sail artisan sail:publish

# Then add to docker/8.3/Dockerfile:
RUN apt-get install -y libmagickwand-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Rebuild
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

### Already Included in Laravel 13 Livewire Starter Kit:
- ✅ livewire/livewire v4.1
- ✅ livewire/flux v2.13
- ✅ laravel/fortify (auth)
- ✅ laravel/sail (docker)
- ✅ pestphp/pest (testing)
- ✅ laravel/pint (code style)
- ✅ Redis (phpredis built-in)

### Redis .env Configuration:
```env
REDIS_CLIENT=phpredis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

---

## 🗄️ Final Database Schema (19 tables)

### Authentication
- `users` (id, name, email, password, role, is_active)

### Media Management
- `media` (id, file_name, file_path, file_url, mime_type, file_type, file_size, width, height, alt_text, title, uploaded_by)

### Product Management
- `categories` (id, name, slug, description, parent_id, media_id, order, is_active)
- `products` (id, name, slug, sku, barcode*, description*, category_id, supplier_id, media_id*, cost_price, selling_price, tax_rate*, discount*, quantity, min_stock_level, unit, status)
- `promotions` (id, name, code*, description*, type, value, max_discount*, min_order_amount*, min_quantity*, buy_quantity*, get_quantity*, product_id*, category_id*, media_id*, usage_limit*, used_count, starts_at*, ends_at*, is_active)

### Customers
- `customers` (id, name, email*, phone*, alternative_phone*, date_of_birth*, gender*, address*, city*, country*, tax_number*, credit_limit, current_balance, total_purchases, total_orders, media_id*, is_active, notes*)

### Supply Chain
- `suppliers` (id, name, company_name*, email*, phone*, alternative_phone*, address*, city*, country*, tax_number*, payment_terms, credit_limit, current_balance, media_id*, is_active, notes*)
- `warehouses` (id, name, code, address*, city*, country*, phone*, email*, manager_id*, capacity*, is_default, is_active, notes*)

### Stock Management
- `stock_movements` (id, product_id, warehouse_id, created_by, type, quantity, before_quantity, after_quantity, unit_cost*, reference_type*, reference_id*, expiry_date*, batch_number*, notes*)

### Purchase Orders
- `purchase_orders` (id, order_number, supplier_id, warehouse_id, created_by, approved_by*, status, subtotal, tax*, discount*, total, paid_amount, order_date, expected_date*, received_date*, notes*)
- `purchase_order_items` (id, purchase_order_id, product_id, quantity, received_quantity, unit_cost, tax_rate*, discount*, subtotal, batch_number*, expiry_date*, notes*)

### Invoices & Payments
- `invoices` (id, invoice_number, warehouse_id, customer_id*, created_by, customer_name, customer_email*, customer_phone*, customer_address*, status, payment_method*, subtotal, tax*, discount*, total, paid_amount, due_amount, invoice_date, due_date*, paid_date*, notes*)
- `invoice_items` (id, invoice_id, product_id, product_name, product_sku, quantity, unit_price, tax_rate*, discount*, subtotal, notes*)
- `payments` (id, payment_number, invoice_id, created_by, amount, method, status, reference*, bank_name*, account_number*, cheque_number*, payment_date, notes*)

### Settings
- `settings` (id, key, value*, group, type, is_public)

> * = optional field (nullable)

### Media Management (WordPress-like)
- `media` (id, file_name, file_path, file_type, file_size, mime_type, alt_text, uploaded_by, created_at)

**Features:**
- Drag & drop upload
- Image resize & optimize (Intervention Image v4 + Imagick)
- Modal picker (select from library anywhere)
- Used for: product images, shop logo, supplier docs, invoice attachments

> ❌ No `product_images` table - replaced by reusable Media Manager

### Supply Chain
- `suppliers` (id, name, email, phone, address, city, country, payment_terms, status)
- `warehouses` (id, name, code, address, manager_id, capacity, status)

### Stock Management
- `stock_movements` (id, product_id, warehouse_id, type, quantity, reference_id, notes, before_quantity, after_quantity, created_by)

**Stock Movement Types:**
- PURCHASE, SALE, RETURN, ADJUSTMENT, TRANSFER, DAMAGED, EXPIRED

### Orders
- `purchase_orders` (id, order_number, supplier_id, total, status, date)
- `purchase_order_items` (id, purchase_order_id, product_id, quantity, price)

### Invoices (Sales)
- `invoices` (id, invoice_number, customer_name, email, phone, address, subtotal, tax, discount, total, status, due_date, paid_date, notes, created_by)
- `invoice_items` (id, invoice_id, product_id, quantity, price, tax, discount)
- `payments` (id, invoice_id, amount, method, date, reference)

**Invoice Status:** draft, sent, paid, overdue, cancelled
**Payment Methods:** cash, card, bank transfer

### Promotions
- `promotions` (id, name, type, value, product_id, category_id, starts_at, ends_at, min_quantity, is_active)

### Customers
- `customers` (id, name, email, phone, address, city, country, credit_limit, current_balance, media_id, is_active)

### Settings
- `settings` (id, key, value, group, is_public)

### Audit
- `activity_log` (via Spatie Activitylog)

---

## 🏗️ Artisan Commands

### Artisan Commands
```bash
# Publish & Migrate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan vendor:publish --tag=laravel-assets
```
./vendor/bin/sail artisan make:model Product -m
./vendor/bin/sail artisan make:model ProductImage -m
./vendor/bin/sail artisan make:model Supplier -m
./vendor/bin/sail artisan make:model Warehouse -m
./vendor/bin/sail artisan make:model StockMovement -m
./vendor/bin/sail artisan make:model PurchaseOrder -m
./vendor/bin/sail artisan make:model PurchaseOrderItem -m
./vendor/bin/sail artisan make:model Invoice -m
./vendor/bin/sail artisan make:model InvoiceItem -m
./vendor/bin/sail artisan make:model Payment -m
```

### Create Livewire Components
```bash
# Media Manager
./vendor/bin/sail artisan make:livewire Media.MediaLibrary
./vendor/bin/sail artisan make:livewire Media.MediaPicker

# Dashboard
./vendor/bin/sail artisan make:livewire Dashboard.Index

# Products
./vendor/bin/sail artisan make:livewire Products.ProductList
./vendor/bin/sail artisan make:livewire Products.ProductForm

# Categories
./vendor/bin/sail artisan make:livewire Categories.CategoryList
./vendor/bin/sail artisan make:livewire Categories.CategoryForm

# Suppliers
./vendor/bin/sail artisan make:livewire Suppliers.SupplierList
./vendor/bin/sail artisan make:livewire Suppliers.SupplierForm

# Warehouses
./vendor/bin/sail artisan make:livewire Warehouses.WarehouseList
./vendor/bin/sail artisan make:livewire Warehouses.WarehouseForm

# Stock
./vendor/bin/sail artisan make:livewire Stock.MovementList
./vendor/bin/sail artisan make:livewire Stock.StockAdjustment
./vendor/bin/sail artisan make:livewire Stock.StockTransfer

# Purchase Orders
./vendor/bin/sail artisan make:livewire Purchases.PurchaseList
./vendor/bin/sail artisan make:livewire Purchases.PurchaseForm

# Invoices (Sales + POS)
./vendor/bin/sail artisan make:livewire Invoices.InvoiceList
./vendor/bin/sail artisan make:livewire Invoices.InvoiceForm
./vendor/bin/sail artisan make:livewire Invoices.InvoiceView
./vendor/bin/sail artisan make:livewire Pos.PosTerminal

# Reports
./vendor/bin/sail artisan make:livewire Reports.StockReport
./vendor/bin/sail artisan make:livewire Reports.SalesReport
./vendor/bin/sail artisan make:livewire Reports.LowStockAlert

# Promotions
./vendor/bin/sail artisan make:livewire Promotions.PromotionList
./vendor/bin/sail artisan make:livewire Promotions.PromotionForm

# Customers
./vendor/bin/sail artisan make:livewire Customers.CustomerList
./vendor/bin/sail artisan make:livewire Customers.CustomerForm

# Settings
./vendor/bin/sail artisan make:livewire Settings.GeneralSettings
./vendor/bin/sail artisan make:livewire Settings.UserManagement
./vendor/bin/sail artisan make:livewire Settings.RoleManagement
```

---

## 🖥️ POS Terminal Layout

```
┌─────────────────────────────────────────┐
│  🔍 [Scan/Search Bar - Always Focused]  │
├──────────────────┬──────────────────────┤
│                  │  Cart Items          │
│  Product Grid    │  ──────────────────  │
│  (Quick Select)  │  Item 1    $10.00   │
│                  │  Item 2    $20.00   │
│                  │  ──────────────────  │
│                  │  Total:    $30.00   │
├──────────────────┴──────────────────────┤
│  [Cash F6] [Card F7] [Split F8] [F9 ✓] │
└─────────────────────────────────────────┘
```

---

## 📋 System Modules

### ✅ Module 1: Authentication & Roles
- [ ] Laravel 13 built-in auth
- [ ] Roles: Admin, Manager, Staff, Viewer
- [ ] Spatie permissions setup
- [ ] Seed default roles & permissions

### ✅ Module 2: Dashboard
- [ ] Sales summary (today/week/month)
- [ ] Low stock alerts
- [ ] Recent transactions
- [ ] Quick action buttons

### ✅ Module 3: Product Management
- [ ] CRUD with variants
- [ ] SKU auto-generation
- [ ] Barcode field (optional, scanner support)
- [ ] Multiple images
- [ ] Low stock level setting
- [ ] Redis cache (15 min)

### ✅ Module 4: Category Management
- [ ] Hierarchical (nested) categories
- [ ] Category tree
- [ ] Redis cache (1 hour)

### ✅ Module 5: Supplier Management
- [ ] Supplier profiles
- [ ] Contact information
- [ ] Purchase history
- [ ] Payment terms

### ✅ Module 6: Warehouse Management
- [ ] Multiple warehouse support
- [ ] Stock per warehouse
- [ ] Transfer between warehouses

### ✅ Module 7: Stock Management
- [ ] Real-time stock tracking
- [ ] Stock movements (IN/OUT/ADJUSTMENT)
- [ ] Stock transfer
- [ ] Expiry date management
- [ ] Redis cache (5 min)

### ✅ Module 8: Purchase Orders
- [ ] Create/approve/receive orders
- [ ] Status workflow (draft→approved→received)
- [ ] Auto stock update on receive
- [ ] Supplier linking

### ✅ Module 9: POS Terminal (Sales)
- [ ] Barcode scanner support (USB plug & play)
- [ ] Product quick search
- [ ] Cart management
- [ ] Keyboard shortcuts
- [ ] Multiple payment methods
- [ ] Receipt printing (thermal + PDF)
- [ ] Split payment support

### ✅ Module 10: Invoice Management
- [ ] Auto invoice number (INV-2025-0001)
- [ ] PDF generation (DomPDF)
- [ ] Email invoice
- [ ] Payment tracking (partial/full)
- [ ] Status tracking

### ✅ Module 11: Reports & Analytics
- [ ] Stock valuation
- [ ] Low stock alerts
- [ ] Sales summary
- [ ] Purchase summary
- [ ] Profit/loss
- [ ] Export PDF/Excel

### ✅ Module 12: Notifications
- [ ] Low stock alerts (email + UI)
- [ ] Order status updates
- [ ] Expiry warnings
- [ ] Redis queued emails

### ✅ Module 13: Audit Log
- [ ] All changes tracked (Spatie)
- [ ] User activity
- [ ] Stock movement history

---

## ⚡ Redis Strategy

| Data | Cache Duration |
|------|---------------|
| Product list | 15 minutes |
| Category tree | 1 hour |
| Stock levels | 5 minutes |
| User permissions | 30 minutes |
| Sessions | Redis driver |
| Queue jobs | Redis driver |

---

## 🔒 Security
- [ ] CSRF protection
- [ ] XSS prevention
- [ ] Role-based access control
- [ ] API rate limiting
- [ ] Input validation on all forms

---

## 🚀 CI/CD (GitHub Actions)
- [ ] Run PHPUnit tests on push
- [ ] Laravel Pint code style check
- [ ] Auto deploy on main branch merge

---

## 🖨️ POS Hardware Support
| Device | Method |
|--------|--------|
| Barcode Scanner (USB) | Keyboard input (plug & play) |
| Thermal Printer | mike42/escpos-php |
| Invoice Printer | DomPDF + browser print |
| Label Printer | DomPDF + CSS |

---

## 📁 Project Structure

```
inventory-management/
├── app/
│   ├── Livewire/
│   │   ├── Dashboard/
│   │   ├── Products/
│   │   ├── Categories/
│   │   ├── Suppliers/
│   │   ├── Warehouses/
│   │   ├── Stock/
│   │   ├── Purchases/
│   │   ├── Invoices/
│   │   ├── Pos/
│   │   ├── Reports/
│   │   └── Settings/
│   ├── Models/
│   ├── Services/
│   └── Jobs/
├── resources/
│   └── views/
│       └── livewire/
├── database/
│   ├── migrations/
│   └── seeders/
└── docker/
```

---

## ✅ Progress Tracker

### Completed:
- ✅ Docker + Laravel Sail setup
- ✅ All 21 migrations (with indexes)
- ✅ All 14 models (Laravel 13 attribute style)
- ✅ Middleware (CheckRole, CheckActive)
- ✅ Routes (web.php with role protection)
- ✅ Sidebar layout (Flux UI, collapsible, wire:navigate)
- ✅ Dashboard module (stats, charts, quick actions)
- ✅ Products module (list, create, edit, delete)
- ✅ Seeders (Admin, Settings, Warehouse, Products)
- ✅ NumberGeneratorService

### In Progress:
- 🔄 Media Manager

### Pending:
- ⏳ Categories
- ⏳ Suppliers
- ⏳ Customers
- ⏳ Stock Management
- ⏳ Purchase Orders
- ⏳ Invoices
- ⏳ POS Terminal
- ⏳ Reports
- ⏳ Settings
- ⏳ Notifications
- ⏳ Activity Log
- ⏳ CI/CD
