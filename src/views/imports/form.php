<h1>Import clients</h1>
<p class="muted">Works with any CSV or Excel (.xlsx) file — a CRM export, a spreadsheet, a course platform's contact list. Upload it, then match its columns to AA New Reality - Client DB's fields on the next screen before anything is saved.</p>

<div class="card" style="max-width:520px;">
  <form method="post" action="<?= e(base_url('clients/import/preview')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="csv">CSV or Excel file</label>
    <input id="csv" type="file" name="csv" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    <div class="actions"><button class="btn primary" type="submit">Upload & preview</button></div>
  </form>
</div>

<div class="card" style="max-width:520px;">
  <h2 style="margin-top:0;">What happens next</h2>
  <p class="muted">The file is parsed and you'll see its columns next to AA New Reality - Client DB's fields, with a best guess already filled in from the header names. Nothing is saved to the database until you confirm the mapping and click Import.</p>
  <p class="muted">Excel files: only the first sheet is read, and only .xlsx (not the older .xls format).</p>
</div>
