(function () {
    const itemsBody = document.getElementById('itemsBody');
    const addBtn = document.getElementById('addItemBtn');
    const form = document.getElementById('invoiceForm');

    const taxRateSelect = document.getElementById('tax_rate_id');

    const subtotalView = document.getElementById('subtotalView');
    const taxableView = document.getElementById('taxableView');
    const taxView = document.getElementById('taxView');
    const totalView = document.getElementById('totalView');

    const formError = document.getElementById('formError');
    const formSuccess = document.getElementById('formSuccess');

    let nextIndex = 0;

    const UNIT_OPTIONS = [
        // count
        { value: 'units', label: 'Units' },

        // time
        { value: 'min', label: 'Minutes' },
        { value: 'hrs', label: 'Hours' },
        { value: 'days', label: 'Days' },
        { value: 'weeks', label: 'Weeks' },
        { value: 'months', label: 'Months' },

        // distance
        { value: 'm', label: 'Meters' },

        // area
        { value: 'sqm', label: 'm²' },

        // volume
        { value: 'l', label: 'Liters' },
        { value: 'cbm', label: 'm³' },

        // mass
        { value: 'kg', label: 'Kilograms' },
        { value: 'g', label: 'Grams' },
        { value: 't', label: 'Tonnes' },

        // packaging
        { value: 'box', label: 'Box' },
        { value: 'pack', label: 'Pack' },
        { value: 'set', label: 'Set' },
    ];

    function formatMoney(n) {
        const x = Number.isFinite(n) ? n : 0;
        return x.toFixed(2);
    }

    function currentTaxRate() {
        const opt = taxRateSelect.options[taxRateSelect.selectedIndex];
        const rate = opt ? parseFloat(opt.getAttribute('data-rate') || '0') : 0;
        return Number.isFinite(rate) ? rate : 0;
    }

    function addRow(initial = {}) {
        const idx = nextIndex++;
        const tr = document.createElement('tr');
        tr.dataset.idx = String(idx);

        const desc = (initial.description || '').replace(/"/g, '&quot;');
        const qty = (initial.quantity ?? 1);
        const price = (initial.unit_price ?? 0);
        const unit = (initial.unit || 'units');
        const unitOptionsHtml = UNIT_OPTIONS.map(u =>
            `<option value="${u.value}" ${u.value === unit ? 'selected' : ''}>${u.label}</option>`
        ).join('');

        const taxedChecked = (initial.taxed === false) ? '' : 'checked';

        tr.innerHTML = `
          <td>
            <input type="text" name="items[description][${idx}]" required maxlength="255" value="${desc}">
          </td>
          <td>
            <input class="qty" type="number" step="0.01" min="0.01" name="items[quantity][${idx}]" required value="${qty}">
          </td>
          <td>
            <select class="unit" name="items[unit][${idx}]" required>
                ${unitOptionsHtml}
            </select>
          </td>
          <td>
            <input class="price" type="number" step="0.01" min="0" name="items[unit_price][${idx}]" required value="${price}">
          </td>
          <td style="text-align:center;">
            <input type="hidden" name="items[taxed][${idx}]" value="0">
            <input class="taxed" type="checkbox" name="items[taxed][${idx}]" value="1" ${taxedChecked}>
          </td>
          <td class="right">
            <span class="lineTotal">0.00</span>
          </td>
          <td>
            <button type="button" class="btn btn-danger removeBtn">X</button>
          </td>
        `;

        itemsBody.appendChild(tr);

        tr.querySelectorAll('input, select').forEach(inp => {
            inp.addEventListener('input', recalc);
            inp.addEventListener('change', recalc);
        });

        tr.querySelector('.removeBtn').addEventListener('click', () => {
            tr.remove();
            recalc();
        });

        recalc();
    }

    function recalc() {
        const rate = currentTaxRate() / 100;

        let subtotal = 0;
        let taxable = 0;

        [...itemsBody.querySelectorAll('tr')].forEach(tr => {
            const qty = parseFloat(tr.querySelector('.qty').value || '0');
            const price = parseFloat(tr.querySelector('.price').value || '0');
            const taxed = tr.querySelector('.taxed').checked;

            const line = (Number.isFinite(qty) ? qty : 0) * (Number.isFinite(price) ? price : 0);
            subtotal += line;
            if (taxed) taxable += line;

            tr.querySelector('.lineTotal').textContent = formatMoney(line);
        });

        const tax = taxable * rate;
        const total = subtotal + tax;

        subtotalView.textContent = formatMoney(subtotal);
        taxableView.textContent = formatMoney(taxable);
        taxView.textContent = formatMoney(tax);
        totalView.textContent = formatMoney(total);
    }

    addBtn.addEventListener('click', () => addRow());
    taxRateSelect.addEventListener('change', recalc);

    addRow({ description: 'Service Fee', quantity: 1, unit: 'units', unit_price: 0, taxed: false });
    addRow({ description: 'Labor',       quantity: 1, unit: 'hrs',   unit_price: 0, taxed: false });
    addRow({ description: 'Parts',       quantity: 1, unit: 'units', unit_price: 0, taxed: false });

    function generateInvoiceNumber() {
        const d = new Date();

        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');

        const bytes = new Uint8Array(3);
        crypto.getRandomValues(bytes);
        const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('').toUpperCase();

        return `INV-${yyyy}${mm}${dd}-${hex}`;
    }

    function resetItemsToDefault() {
        itemsBody.innerHTML = '';
        nextIndex = 0;

        addRow({ description: 'Service Fee', quantity: 1, unit: 'units', unit_price: 0, taxed: false });
        addRow({ description: 'Labor',       quantity: 1, unit: 'hrs',   unit_price: 0, taxed: false });
        addRow({ description: 'Parts',       quantity: 1, unit: 'units', unit_price: 0, taxed: false });

        recalc();
    }

    function resetInvoiceForm() {
        form.reset();

        resetItemsToDefault();

        formError.style.display = 'none';
        formError.textContent = '';

        form.invoice_number.value = generateInvoiceNumber();
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        formError.style.display = 'none';
        formSuccess.style.display = 'none';
        formError.textContent = '';

        if (!form.company_id.value || !form.client_id.value || !form.invoice_number.value || !form.invoice_date.value || !form.due_date.value) {
            formError.textContent = 'Please complete all required invoice fields.';
            formError.style.display = 'block';
            return;
        }

        if (itemsBody.querySelectorAll('tr').length === 0) {
            formError.textContent = 'Please add at least one invoice item.';
            formError.style.display = 'block';
            return;
        }

        const fd = new FormData(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            });

            const data = await res.json().catch(() => null);

            if (!res.ok || !data || data.ok !== true) {
                const msg = (data && data.error) ? data.error : 'Failed to save invoice.';
                formError.textContent = msg;
                formError.style.display = 'block';
                return;
            }

            formSuccess.innerHTML = `Saved. Invoice ID: <strong>${data.invoice_id}</strong>`;
            formSuccess.style.display = 'block';

            resetInvoiceForm();
        } catch (err) {
            formError.textContent = 'Network or server error. Please try again.';
            formError.style.display = 'block';
        }
    });
})();