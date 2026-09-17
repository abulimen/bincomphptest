<?php
require 'database.php';

$lgaList = mysqli_fetch_all(mysqli_query($conn, 'SELECT lga_id, lga_name FROM lga ORDER BY lga_name'), MYSQLI_ASSOC);

$wardList = [];
$pollingUnitList = [];
$partyResults = null;
$pollingUnitName = '';
$lgaId = '';
$wardId = '';
$pollingUnitId = '';
if (isset($_GET['lga_id'])) { $lgaId = $_GET['lga_id']; }
if (isset($_GET['ward_id'])) { $wardId = $_GET['ward_id']; }
if (isset($_GET['polling_unit_id'])) { $pollingUnitId = $_GET['polling_unit_id']; }

if ($lgaId) {
    $statement = mysqli_prepare($conn, 'SELECT uniqueid, ward_name FROM ward WHERE lga_id = ? ORDER BY ward_name');
    mysqli_stmt_bind_param($statement, 'i', $lgaId);
    mysqli_stmt_execute($statement);
    $wardList = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
}
if ($wardId) {
    $statement = mysqli_prepare($conn, 'SELECT uniqueid, polling_unit_name FROM polling_unit
                                        WHERE uniquewardid = ? AND polling_unit_name <> "" ORDER BY polling_unit_name');
    mysqli_stmt_bind_param($statement, 'i', $wardId);
    mysqli_stmt_execute($statement);
    $pollingUnitList = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
}
if ($pollingUnitId) {
    $statement = mysqli_prepare($conn, 'SELECT polling_unit_name, polling_unit_number FROM polling_unit WHERE uniqueid = ?');
    mysqli_stmt_bind_param($statement, 'i', $pollingUnitId);
    mysqli_stmt_execute($statement);
    $pollingUnit = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    if ($pollingUnit) {
        $pollingUnitName = $pollingUnit['polling_unit_name'] . ' (' . $pollingUnit['polling_unit_number'] . ')';
        $statement = mysqli_prepare($conn, 'SELECT party_abbreviation, party_score FROM announced_pu_results
                                            WHERE polling_unit_uniqueid = ? ORDER BY party_abbreviation');
        mysqli_stmt_bind_param($statement, 'i', $pollingUnitId);
        mysqli_stmt_execute($statement);
        $partyResults = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Polling Unit Results</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width: 640px;"><nav class="mb-3"><a href="index.php">&larr; Home</a></nav>
<h1>Polling Unit Results</h1>
<form method="get">
    <div>
        <label>LGA</label>
        <select class="form-select d-inline-block w-auto" name="lga_id" onchange="this.form.submit()" required>
            <option value="">-- select LGA --</option>
            <?php foreach ($lgaList as $lga): ?>
            <option value="<?= $lga['lga_id'] ?>" <?php if ($lga['lga_id'] == $lgaId) { ?>selected<?php } ?> ><?= $lga['lga_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($wardList): ?>
    <div>
        <label>Ward</label>
        <select class="form-select d-inline-block w-auto" name="ward_id" onchange="this.form.submit()" required>
            <option value="">-- select ward --</option>
            <?php foreach ($wardList as $ward): ?>
            <option value="<?= $ward['uniqueid'] ?>" <?php if ($ward['uniqueid'] == $wardId) { ?>selected<?php } ?> ><?= $ward['ward_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php if ($pollingUnitList): ?>
    <div>
        <label>Polling Unit</label>
        <select class="form-select d-inline-block w-auto" name="polling_unit_id" onchange="this.form.submit()" required>
            <option value="">-- select polling unit --</option>
            <?php foreach ($pollingUnitList as $pollingUnit): ?>
            <option value="<?= $pollingUnit['uniqueid'] ?>" <?php if ($pollingUnit['uniqueid'] == $pollingUnitId) { ?>selected<?php } ?> ><?= $pollingUnit['polling_unit_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <noscript><button type="submit" class="btn btn-outline-secondary">Go</button></noscript>
</form>

<?php if ($partyResults === null && isset($_GET['polling_unit_id'])): ?>
<p class="alert alert-danger">Polling unit not found.</p>
<?php elseif ($partyResults !== null): ?>
<h2><?= $pollingUnitName ?></h2>
<?php if ($partyResults): ?>
<table class="table table-striped table-bordered">
    <tr><th>Party</th><th>Score</th></tr>
    <?php foreach ($partyResults as $partyResult): ?>
    <tr><td><?= $partyResult['party_abbreviation'] ?></td><td><?= (int)$partyResult['party_score'] ?></td></tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p class="alert alert-danger">No results have been announced for this polling unit.</p>
<?php endif; ?>
<?php elseif ($wardId && !$pollingUnitList): ?>
<p class="alert alert-info">No polling units are listed for this ward.</p>
<?php endif; ?>
</div>
</body>
</html>
