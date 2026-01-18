<?php
require_once dirname(__DIR__) . '/config/db.php';

function sanitize(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$conn = db();

$companies = [];
$res = $conn->query("SELECT id, name, email, phone, website FROM companies ORDER BY name");
while ($row = $res->fetch_assoc()) {
    $companies[] = $row;
}

$clients = [];
$res = $conn->query("SELECT id, name, company_name, email, customer_code FROM clients ORDER BY company_name, name");
while ($row = $res->fetch_assoc()) {
    $clients[] = $row;
}

$taxRates = [];
$res = $conn->query("SELECT id, name, rate FROM tax_rates WHERE active=1 ORDER BY rate DESC");
while ($row = $res->fetch_assoc()) {
    $taxRates[] = $row;
}

$defaultInvoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Capture Invoice</title>
    <link rel="stylesheet" href="assets/css/invoice.css">
</head>
<body>

<h2>Invoice Capture</h2>

<form id="invoiceForm" method="post" action="save_invoice.php" novalidate>
    <div class="row">
        <div class="col">
            <label for="company_id">Issuer Company</label>
            <select name="company_id" id="company_id" required>
                <option value="">-- Select company --</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= sanitize((string)$company['id']) ?>"><?= sanitize($company['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col">
            <label for="client_id">Bill To (Client)</label>
            <select name="client_id" id="client_id" required>
                <option value="">-- Select client --</option>
                <?php foreach ($clients as $client): ?>
                    <?php
                    $label = trim(($client['company_name'] ?? '') . ' - ' . ($client['name'] ?? ''));
                    $label = $label !== '-' ? $label : ($client['email'] ?? 'Client');
                    ?>
                    <option value="<?= sanitize((string)$client['id']) ?>">
                        <?= sanitize($label) ?><?= $client['customer_code'] ? ' (' . sanitize($client['customer_code']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col">
            <label for="invoice_number">Invoice #</label>
            <input type="text" name="invoice_number" id="invoice_number" value="<?= sanitize($defaultInvoiceNo) ?>" required maxlength="50">
        </div>

        <div class="col">
            <label for="invoice_date">Invoice Date</label>
            <input type="date" name="invoice_date" id="invoice_date" value="<?= sanitize(date('Y-m-d')) ?>" required>
        </div>

        <div class="col">
            <label for="due_date">Due Date</label>
            <input type="date" name="due_date" id="due_date" value="<?= sanitize(date('Y-m-d', strtotime('+30 days'))) ?>" required>
        </div>

        <div class="col">
            <label for="tax_rate_id">Tax Rate</label>
            <select name="tax_rate_id" id="tax_rate_id">
                <option value="">-- None --</option>
                <?php foreach ($taxRates as $taxRate): ?>
                    <option value="<?= sanitize((string)$taxRate['id']) ?>" data-rate="<?= sanitize((string)$taxRate['rate']) ?>">
                        <?= sanitize($taxRate['name']) ?> (<?= sanitize((string)$taxRate['rate']) ?>%)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">Used only on "taxed" line items.</small>
        </div>
    </div>

    <h3 style="margin-top:18px;">Items</h3>
    <table id="itemsTable">
        <thead>
        <tr>
            <th style="width:40%;">Description</th>
            <th style="width:12%;">Qty</th>
            <th style="width:10%;">Unit</th>
            <th style="width:16%;">Unit Price</th>
            <th style="width:8%;">Taxed</th>
            <th style="width:17%;" class="right">Line Total</th>
            <th style="width:0;"></th>
        </tr>
        </thead>
        <tbody id="itemsBody">
            <!-- Insert rows using JS -->
        </tbody>
    </table>

    <button type="button" class="btn btn-secondary" id="addItemBtn">+ Add item</button>

    <div class="totals">
        <div><span>Subtotal</span><strong id="subtotalView">0.00</strong></div>
        <div><span>Taxable</span><strong id="taxableView">0.00</strong></div>
        <div><span>Tax</span><strong id="taxView">0.00</strong></div>
        <div style="border-top:1px solid #ddd; margin-top:6px; padding-top:10px;">
            <span>Total</span><strong id="totalView">0.00</strong>
        </div>
    </div>

    <label for="notes">Other Comments / Notes</label>
    <textarea name="notes" id="notes" rows="4" maxlength="5000"></textarea>

    <div class="row" style="margin-top:14px;">
        <button type="submit" class="btn btn-primary">Save Invoice</button>
    </div>

    <div class="error" id="formError" style="display:none;"></div>
    <div id="formSuccess" style="display:none; margin-top:10px;"></div>
</form>

<script src="assets/js/invoice.js?v=<?= time() ?>"></script>
</body>
</html>