<?php
declare(strict_types=1);
// Design reference (Stage 1 components). Admin only. No data is saved here.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_permission('settings.manage');
render_header('UI Kit');
?>
<div class="page-head">
  <div><h1>UI foundation</h1><p>Components that every module in this system is built from.</p></div>
  <button class="btn btn-primary" data-action="toast" data-type="success" data-message="This is a success message">Show notification</button>
</div>
<div class="stats">
  <div class="card stat"><div class="label">Today's sales</div><div class="value">Rs. 0.00</div></div>
  <div class="card stat good"><div class="label">Cash in hand</div><div class="value">Rs. 0.00</div></div>
  <div class="card stat warn"><div class="label">Low stock products</div><div class="value">0</div></div>
  <div class="card stat bad"><div class="label">Customer receivables</div><div class="value">Rs. 0.00</div></div>
</div>
<div class="grid-2">
  <section class="card"><div class="card-head"><h2>Form controls</h2></div>
    <div class="card-body form-grid">
      <div class="field"><label for="a">Product name</label><input id="a" type="text" placeholder="Search or type"></div>
      <div class="field"><label for="b">Unit</label><select id="b"><option>Piece</option><option>Gallon</option></select></div>
      <div class="field"><label for="c">Sale price</label><input id="c" type="number" class="invalid"><div class="err">Enter a price greater than zero.</div></div>
    </div></section>
  <section class="card"><div class="card-head"><h2>Buttons and status</h2></div>
    <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <button class="btn btn-primary">Save invoice</button><button class="btn">Cancel</button><button class="btn btn-success">Collect payment</button><button class="btn btn-danger">Delete</button>
      <span class="badge success">Synced</span><span class="badge warning">Pending</span><span class="badge danger">Failed</span><span class="badge info">Held</span>
    </div></section>
</div>
<?php render_footer();
