<?php
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(int $code, array $payload) : never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function parse_decimal($v) : ?float
{
    if ($v === null) return null;

    $s = trim((string)$v);
    if ($s === '') return null;

    // allow up to 4 decimals
    if (!preg_match('/^\d+(\.\d{1,4})?$/', $s)) return null;

    return (float)$s;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_out(405, ['ok' => false, 'error' => 'Method not allowed']);
    }

    $companyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    $clientId  = isset($_POST['client_id'])  ? (int)$_POST['client_id']  : 0;

    $taxRateId = null;
    if (isset($_POST['tax_rate_id']) && $_POST['tax_rate_id'] !== '') {
        $taxRateId = (int)$_POST['tax_rate_id'];
        if ($taxRateId <= 0) $taxRateId = null;
    }

    $invoiceNumber = isset($_POST['invoice_number']) ? trim((string)$_POST['invoice_number']) : '';
    $invoiceDate   = isset($_POST['invoice_date'])   ? trim((string)$_POST['invoice_date'])   : '';
    $dueDate       = isset($_POST['due_date'])       ? trim((string)$_POST['due_date'])       : '';
    $notes         = isset($_POST['notes'])          ? trim((string)$_POST['notes'])          : null;

    if ($companyId <= 0 || $clientId <= 0 || $invoiceNumber === '' || $invoiceDate === '' || $dueDate === '') {
        json_out(422, ['ok' => false, 'error' => 'Missing required fields.']);
    }

    if (mb_strlen($invoiceNumber) > 50) {
        json_out(422, ['ok' => false, 'error' => 'Invoice number is too long.']);
    }
    if (mb_strlen($invoiceDate) > 10 || mb_strlen($dueDate) > 10) {
        json_out(422, ['ok' => false, 'error' => 'Invalid date format.']);
    }
    if ($notes !== null && $notes !== '' && mb_strlen($notes) > 5000) {
        json_out(422, ['ok' => false, 'error' => 'Notes is too long.']);
    }

    if ($notes !== null) {
        $notes = ($notes === '') ? null : $notes;
    }

    $d1 = DateTime::createFromFormat('Y-m-d', $invoiceDate);
    $d2 = DateTime::createFromFormat('Y-m-d', $dueDate);

    if (!$d1 || $d1->format('Y-m-d') !== $invoiceDate) {
        json_out(422, ['ok' => false, 'error' => 'Invalid invoice date.']);
    }
    if (!$d2 || $d2->format('Y-m-d') !== $dueDate) {
        json_out(422, ['ok' => false, 'error' => 'Invalid due date.']);
    }

    $items = $_POST['items'] ?? null;
    if (!is_array($items)) {
        json_out(422, ['ok' => false, 'error' => 'Items are required.']);
    }

    $descArr  = $items['description'] ?? [];
    $qtyArr   = $items['quantity'] ?? [];
    $unitArr  = $items['unit'] ?? [];
    $priceArr = $items['unit_price'] ?? [];
    $taxedArr = $items['taxed'] ?? [];

    if (!is_array($descArr) || !is_array($qtyArr) || !is_array($unitArr) || !is_array($priceArr) || !is_array($taxedArr)) {
        json_out(422, ['ok' => false, 'error' => 'Invalid items payload.']);
    }

    if (count($descArr) === 0) {
        json_out(422, ['ok' => false, 'error' => 'Please add at least one invoice item.']);
    }

    $conn = db();

    $stmt = $conn->prepare("SELECT id FROM companies WHERE id=?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        json_out(422, ['ok' => false, 'error' => 'Invalid company selected.']);
    }

    $stmt = $conn->prepare("SELECT id FROM clients WHERE id=?");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        json_out(422, ['ok' => false, 'error' => 'Invalid client selected.']);
    }

    $taxRate = 0.0;
    if ($taxRateId !== null) {
        $stmt = $conn->prepare("SELECT rate FROM tax_rates WHERE id=? AND active=1");
        $stmt->bind_param("i", $taxRateId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            json_out(422, ['ok' => false, 'error' => 'Invalid tax rate selected.']);
        }
        $taxRate = (float)$row['rate'];
    }

    $cleanItems = [];
    $subtotal = 0.0;
    $taxable  = 0.0;

    $itemNumber = 0;

    foreach ($descArr as $idx => $descRaw) {
        $itemNumber++;

        $desc = trim((string)$descRaw);
        if ($desc === '' || mb_strlen($desc) > 255) {
            json_out(422, ['ok' => false, 'error' => "Item #{$itemNumber} has an invalid description."]);
        }

        if (!array_key_exists($idx, $qtyArr) || !array_key_exists($idx, $priceArr)) {
            json_out(422, ['ok' => false, 'error' => "Item #{$itemNumber} is missing quantity or unit price."]);
        }

        $qty = parse_decimal($qtyArr[$idx]);
        $allowedUnits = [
            'units',
            'min', 'hrs', 'days', 'weeks', 'months',
            'm',
            'sqm',
            'l', 'cbm',
            'kg', 'g', 't',
            'box', 'pack', 'set',
        ];
        $unitRaw = $unitArr[$idx] ?? null;
        $unit = trim((string)$unitRaw);
        $unitPrice = parse_decimal($priceArr[$idx]);

        if ($qty === null || $qty <= 0) {
            json_out(422, ['ok' => false, 'error' => "Item #{$itemNumber} has an invalid quantity."]);
        }
        if ($unit === '' || !in_array($unit, $allowedUnits, true)) {
            json_out(422, ['ok' => false, 'error' => "Item #{$itemNumber} has an invalid unit."]);
        }
        if ($unitPrice === null || $unitPrice < 0) {
            json_out(422, ['ok' => false, 'error' => "Item #{$itemNumber} has an invalid unit price."]);
        }

        $taxedVal = $taxedArr[$idx] ?? '0';

        if (is_array($taxedVal)) {
            $last = end($taxedVal);
            $taxed = ((string)$last === '1') ? 1 : 0;
        } else {
            $taxed = ((string)$taxedVal === '1') ? 1 : 0;
        }

        $lineTotal = round($qty * $unitPrice, 2);

        $subtotal += $lineTotal;
        if ($taxed === 1) {
            $taxable += $lineTotal;
        }

        $cleanItems[] = [
            'description' => $desc,
            'quantity' => $qty,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'taxed' => $taxed,
            'line_total' => $lineTotal,
        ];
    }

    $tax = round($taxable * ($taxRate / 100.0), 2);
    $total = round($subtotal + $tax, 2);

    $conn->begin_transaction();

    $status = 'Draft';

    $stmt = $conn->prepare("
        INSERT INTO invoices
          (company_id, client_id, tax_rate_id, invoice_number, invoice_date, due_date, status, subtotal, tax, total, notes)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $taxRateIdBind = $taxRateId ?? null;

    $stmt->bind_param(
        "iiissssddds",
        $companyId,
        $clientId,
        $taxRateIdBind,
        $invoiceNumber,
        $invoiceDate,
        $dueDate,
        $status,
        $subtotal,
        $tax,
        $total,
        $notes
    );

    if (!$stmt->execute()) {
        $conn->rollback();

        if ((int)$conn->errno === 1062) {
            json_out(409, ['ok' => false, 'error' => 'Invoice number already exists for this company.']);
        }

        json_out(500, ['ok' => false, 'error' => 'Failed to save invoice header.']);
    }

    $invoiceId = (int)$conn->insert_id;

    $stmtItem = $conn->prepare("
        INSERT INTO invoice_items
          (invoice_id, description, quantity, unit, unit_price, line_total, taxed)
        VALUES
          (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($cleanItems as $it) {
        $stmtItem->bind_param(
            "isdsddi",
            $invoiceId,
            $it['description'],
            $it['quantity'],
            $it['unit'],
            $it['unit_price'],
            $it['line_total'],
            $it['taxed']
        );

        if (!$stmtItem->execute()) {
            $conn->rollback();
            json_out(500, ['ok' => false, 'error' => 'Failed to save invoice items.']);
        }
    }

    $conn->commit();

    json_out(201, [
        'ok' => true,
        'invoice_id' => $invoiceId,
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'tax' => number_format($tax, 2, '.', ''),
        'total' => number_format($total, 2, '.', ''),
    ]);

} catch (Throwable $e) {
    json_out(500, ['ok' => false, 'error' => 'Unexpected server error.']);
}