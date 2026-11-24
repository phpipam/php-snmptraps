<?php
/** @var Snmptraps\Web\App $app */
/** @var list<string> $hosts */
/** @var string|null $hostname */
use Snmptraps\Web\TrapPresenter;

$badges = static function (array $hosts, ?string $active, bool $br): string {
    $out = '';
    foreach ($hosts as $h) {
        $cls = $h === $active ? ' badge-active' : '';
        $out .= "<span class='badge badge1 badge5 merged$cls'><a href='host/" . e(rawurlencode($h)) . "/'>" . e($h) . '</a></span>' . ($br ? '<br>' : ' ');
    }
    return $out;
};
?>
<?php if ($hostname === null): ?>
    <h4>Select host</h4>
    <div class="container-fluid text-left row">
        <div class="col-md-6 col-sm-12">
            <?= $hosts === [] ? '<div class="alert alert-info">No hosts found</div>' : $badges($hosts, null, true) ?>
        </div>
    </div>
<?php else: ?>
    <h4>Traps for host <?= e($hostname) ?></h4>
    <div class="container-fluid text-left row">
        <div class="col-lg-6 col-sm-12"></div>
        <div class="col-lg-6 col-sm-12 hosts-wrapper"><?= $badges($hosts, $hostname, false) ?></div>
    </div>
    <hr><br>
    <div class="container-fluid row" style="margin-bottom:20px;">
        <table class="table snmp sorted sorted-ajax table-noborder table-condensed table-hover" data-url="ajax/traps/?scope=host&amp;type=<?= e(rawurlencode($hostname)) ?>">
            <?= $app->presenter->headers(TrapPresenter::LIST_COLUMNS, true) ?>
        </table>
    </div>
<?php endif; ?>
