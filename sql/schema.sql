CREATE DATABASE IF NOT EXISTS invoicing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE invoicing;

-- Companies
CREATE TABLE companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address_line1 VARCHAR(150) NULL,
    address_line2 VARCHAR(150) NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(80) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(80) NULL,
    phone VARCHAR(30) NULL,
    fax VARCHAR(30) NULL,
    website VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Seed companies
INSERT INTO companies (name, address_line1, address_line2, city, state, postal_code, country, phone, fax, website, email) VALUES
    (
        'SuperTech Solutions (Pty) Ltd',
        '12 Long Street',
        'Floor 4, Suite 401',
        'Cape Town',
        'Western Cape',
        '8001',
        'South Africa',
        '+27 21 555 5555',
        '+27 21 555 5556',
        'www.supertech.co.za',
        'accounts@supertech.co.za'
    );

-- Clients
CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    company_name VARCHAR(150) NULL,
    email VARCHAR(150) NOT NULL,
    customer_code VARCHAR(50) NULL,
    phone VARCHAR(30) NULL,
    address_line1 VARCHAR(150) NULL,
    address_line2 VARCHAR(150) NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(80) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clients_email (email),
    UNIQUE KEY uq_clients_customer_code (customer_code)
);

-- Seed clients
INSERT INTO clients (name, company_name, email, customer_code, phone, address_line1, address_line2, city, state, postal_code, country) VALUES
    (
      'Accounts Dept',
      'Acme Technologies Ltd',
      'accounts@acme.co.za',
      'ACME-001',
      '+27 11 555 0101',
      '101 Rivonia Road',
      'Sandton',
      'Johannesburg',
      'Gauteng',
      '2196',
      'South Africa'
    ),
    (
      'Billing Dept',
      'Green Energy SA',
      'billing@greenenergy.co.za',
      'GES-001',
      '+27 21 555 0202',
      '22 Bree Street',
      'CBD',
      'Cape Town',
      'Western Cape',
      '8001',
      'South Africa'
    );

-- Tax rates
CREATE TABLE tax_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    rate DECIMAL(5,2) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT 1
);

-- Seed tax rates
INSERT INTO tax_rates (name, rate) VALUES ('VAT 15%', 15.00);

-- Invoices
CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    tax_rate_id INT UNSIGNED NULL,
    invoice_number VARCHAR(50) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Draft', 'Sent', 'Paid', 'Cancelled') NOT NULL DEFAULT 'Draft',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_invoices_company
      FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,

    CONSTRAINT fk_invoices_client
      FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,

    CONSTRAINT fk_invoices_tax_rate
      FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL,

    UNIQUE KEY uq_invoice_company_number (company_id, invoice_number),
    INDEX idx_invoices_company (company_id),
    INDEX idx_invoices_client (client_id),
    INDEX idx_invoices_date (invoice_date)
);

-- Invoice items
CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    taxed BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_items_invoice
       FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,

    CONSTRAINT chk_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_items_unit_price CHECK (unit_price >= 0),
    CONSTRAINT chk_items_line_total CHECK (line_total >= 0),

    INDEX idx_items_invoice (invoice_id)
);
