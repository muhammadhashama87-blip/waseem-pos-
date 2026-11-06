<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/dashboard.php';

$user = require_permission('dashboard.view');
$d = dashboard_data($user);
render_header('Dashboard', 'dashboard.php');
?>
<div id="dash" data-api="<?= e(url('api/dashboard/stats.php')) ?>" data-login="<?= e(url('login.php')) ?>">
<div class="page-head">
  <div><h1>Dashboard</h1><p>Figures come straight from the database<?= $d['own_only'] ? ' and show your own sales' : '' ?>.</p></div>
  <div class="dash-meta">
    <span data-conn class="pill">Checking</span>
    Last sync: <b id="last-sync"><?= $d['last_sync'] !== '0' ? e($d['last_sync']) : 'Never' ?></b> &middot; Updated <b id="updated-at"><?= e(date('H:i:s')) ?></b>
  </div>
</div>

<div class="stats">
<?php foreach (dashboard_tiles($d) as [$key, $label, $val, $style]): ?>
  <div class="card stat <?= e($style) ?>"><div class="label"><?= e($label) ?></div><div class="value" data-stat="<?= e($key) ?>"><?= e($val) ?></div></div>
<?php endforeach; ?>
</div>

<div class="grid-2">
  <section class="card"><div class="card-head"><h2>Sales, last 7 days</h2></div><div class="card-body"><div class="chart" data-chart="sales7"></div></div></section>
  <?php if (isset($d['charts']['monthly'])): ?>
  <section class="card"><div class="card-head"><h2>Monthly revenue, last 12 months</h2></div><div class="card-body"><div class="chart" data-chart="monthly"></div></div></section>
  <?php endif; ?>
</div>

<div class="grid-2" style="margin-top:16px">
  <section class="card"><div class="card-head"><h2>Recent sales</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Customer</th><th class="r">Total</th><th class="r">Balance</th></tr></thead><tbody>
    <?php foreach ($d['recent_sales'] as $r): ?>
      <tr><td><?= e($r['invoice_no']) ?><br><small class="muted"><?= e($r['sale_date']) ?></small></td><td><?= e($r['customer']) ?></td><td class="r"><?= e(money($r['total'])) ?></td><td class="r"><?= e(money($r['balance'])) ?></td></tr>
    <?php endforeach; if (!$d['recent_sales']): ?><tr><td colspan="4" class="empty">No sales yet. Invoices created in POS Billing (Stage 4) appear here.</td></tr><?php endif; ?>
    </tbody></table></div></section>

  <?php if (isset($d['recent_purchases'])): ?>
  <section class="card"><div class="card-head"><h2>Recent purchases</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Purchase</th><th>Supplier</th><th class="r">Total</th><th class="r">Payable</th></tr></thead><tbody>
    <?php foreach ($d['recent_purchases'] as $r): ?>
      <tr><td><?= e($r['purchase_no']) ?><br><small class="muted"><?= e($r['purchase_date']) ?></small></td><td><?= e($r['supplier']) ?></td><td class="r"><?= e(money($r['total'])) ?></td><td class="r"><?= e(money($r['balance'])) ?></td></tr>
    <?php endforeach; if (!$d['recent_purchases']): ?><tr><td colspan="4" class="empty">No purchases yet. They appear here once entered in Stage 6.</td></tr><?php endif; ?>
    </tbody></table></div></section>
  <?php endif; ?>

  <section class="card"><div class="card-head"><h2>Top selling products (30 days)</h2></div>
    <?php if ($d['top_products']): ?><ul class="list-plain">
      <?php foreach ($d['top_products'] as $r): ?><li><span><?= e($r['product_name']) ?> <small class="muted">&times; <?= e(rtrim(rtrim(number_format((float)$r['qty'], 3, '.', ''), '0'), '.')) ?></small></span><b class="num"><?= e(money($r['amount'])) ?></b></li><?php endforeach; ?>
    </ul><?php else: ?><div class="empty">Top sellers appear after the first sales.</div><?php endif; ?></section>

  <?php if (isset($d['stock_alerts'])): ?>
  <section class="card"><div class="card-head"><h2>Stock alerts</h2></div>
    <?php if ($d['stock_alerts']): ?><ul class="list-plain">
      <?php foreach ($d['stock_alerts'] as $r): ?><li><span><?= e($r['name']) ?></span><span class="badge <?= (float)$r['current_stock'] <= 0 ? 'danger' : 'warning' ?>"><?= e(rtrim(rtrim(number_format((float)$r['current_stock'], 3, '.', ''), '0'), '.')) ?> <?= e($r['short_name']) ?> left (min <?= e(rtrim(rtrim(number_format((float)$r['min_stock'], 3, '.', ''), '0'), '.')) ?>)</span></li><?php endforeach; ?>
    </ul><?php else: ?><div class="empty">No products are at or below their minimum stock.</div><?php endif; ?></section>
  <?php endif; ?>
</div>
<script type="application/json" id="dash-data"><?= json_encode($d['charts'], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>" defer></script>
<?php render_footer();
