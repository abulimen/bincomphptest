<?php
require 'database.php';

$lgaList = mysqli_fetch_all(mysqli_query($conn, 'SELECT lga_id, lga_name FROM lga ORDER BY lga_name'), MYSQLI_ASSOC);
$partyList = mysqli_fetch_all(mysqli_query($conn, 'SELECT partyid FROM party ORDER BY partyid'), MYSQLI_ASSOC);

$wardList = [];
$successMessage = '';
$errorMessage = '';
$lgaId = '';
$wardId = '';
if (isset($_POST['lga_id'])) { $lgaId = $_POST['lga_id']; }
if (isset($_GET['lga_id'])) { $lgaId = $_GET['lga_id']; }
if (isset($_POST['ward_id'])) { $wardId = $_POST['ward_id']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scores'])) {
    $pollingUnitName = '';
    $enteredBy = '';
    if (isset($_POST['polling_unit_name'])) { $pollingUnitName = trim($_POST['polling_unit_name']); }
    if (isset($_POST['entered_by_user'])) { $enteredBy = trim($_POST['entered_by_user']); }
    if (!$wardId || $pollingUnitName === '' || $enteredBy === '') {
        $errorMessage = 'Ward, polling unit name and your name are required.';
    } else {
        $pollingUnitNumber = '';
        if (isset($_POST['polling_unit_number'])) { $pollingUnitNumber = $_POST['polling_unit_number']; }
        $userIp = '';
        if (isset($_SERVER['REMOTE_ADDR'])) { $userIp = $_SERVER['REMOTE_ADDR']; }
        $statement = mysqli_prepare($conn, 'INSERT INTO polling_unit
            (polling_unit_id, ward_id, lga_id, uniquewardid, polling_unit_number, polling_unit_name, entered_by_user, date_entered, user_ip_address)
            VALUES (0, ?, ?, ?, ?, ?, ?, NOW(), ?)');
        mysqli_stmt_bind_param($statement, 'iiissss', $wardId, $lgaId, $wardId, $pollingUnitNumber, $pollingUnitName, $enteredBy, $userIp);
        mysqli_stmt_execute($statement);
        $newPollingUnitId = mysqli_insert_id($conn);

        $insertStatement = mysqli_prepare($conn, 'INSERT INTO announced_pu_results
            (polling_unit_uniqueid, party_abbreviation, party_score, entered_by_user, date_entered, user_ip_address)
            VALUES (?, ?, ?, ?, NOW(), ?)');
        foreach ($partyList as $party) {
            $partyId = substr($party['partyid'], 0, 4);
            $partyScore = 0;
            if (isset($_POST['scores'][$party['partyid']])) { $partyScore = (int)$_POST['scores'][$party['partyid']]; }
            mysqli_stmt_bind_param($insertStatement, 'isiss', $newPollingUnitId, $partyId, $partyScore, $enteredBy, $userIp);
            mysqli_stmt_execute($insertStatement);
        }
        $successMessage = 'Results saved for ' . $pollingUnitName . '.';
    }
}

if ($lgaId) {
    $statement = mysqli_prepare($conn, 'SELECT uniqueid, ward_name FROM ward WHERE lga_id = ? ORDER BY ward_name');
    mysqli_stmt_bind_param($statement, 'i', $lgaId);
    mysqli_stmt_execute($statement);
    $wardList = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Polling Unit and Results</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width: 640px;"><nav class="mb-3"><a href="index.php">&larr; Home</a></nav>
<h1>Add a Polling Unit and Its Results</h1>
<?php if ($successMessage): ?><p class="alert alert-success"><?= $successMessage ?></p><?php endif; ?>
<?php if ($errorMessage): ?><p class="alert alert-danger"><?= $errorMessage ?></p><?php endif; ?>

<form method="post">
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
        <select class="form-select d-inline-block w-auto" name="ward_id" required>
            <option value="">-- select ward --</option>
            <?php foreach ($wardList as $ward): ?>
            <option value="<?= $ward['uniqueid'] ?>" <?php if ($ward['uniqueid'] == $wardId) { ?>selected<?php } ?> ><?= $ward['ward_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><label>Polling unit name</label><input name="polling_unit_name" class="form-control d-inline-block w-auto" required></div>
    <div><label>Polling unit number</label><input name="polling_unit_number" class="form-control d-inline-block w-auto"></div>
    <div><label>Entered by</label><input name="entered_by_user" class="form-control d-inline-block w-auto" required></div>
    <table class="table table-striped table-bordered">
        <tr><th>Party</th><th>Score</th></tr>
        <?php foreach ($partyList as $party): ?>
        <tr><td><?= $party['partyid'] ?></td><td><input type="number" class="form-control" name="scores[<?= $party['partyid'] ?>]" min="0" value="0" required></td></tr>
        <?php endforeach; ?>
    </table>
    <div class="mt-3"><button type="submit" class="btn btn-success">Save Results</button></div>
    <?php endif; ?>
    <noscript><button type="submit" class="btn btn-outline-secondary">Next</button></noscript>
</form>
</div>
</body>
</html>
