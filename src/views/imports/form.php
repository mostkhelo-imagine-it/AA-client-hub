<h1>Import clients from CSV</h1>
<p class="muted">Works with any CSV — a CRM export, a spreadsheet, a course platform's contact list. Upload it, then match its columns to Client Hub's fields on the next screen before anything is saved.</p>

<div class="card" style="max-width:520px;">
  <form method="post" action="<?= e(base_url('clients/import/preview')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="csv">CSV file</label>
    <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
    <div class="actions"><button class="btn primary" type="submit">Upload & preview</button></div>
  </form>
</div>

<div class="card" style="max-width:520px;">
  <h2 style="margin-top:0;">What happens next</h2>
  <p class="muted">The file is parsed and you'll see its columns next to Client Hub's fields, with a best guess already filled in from the header names. Nothing is saved to the database until you confirm the mapping and click Import.</p>
</div>
