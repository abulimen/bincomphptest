<?php
require 'database.php';

$lgaList = mysqli_fetch_all(mysqli_query($conn, 'SELECT lga_id, lga_name FROM lga ORDER BY lga_name'), MYSQLI_ASSOC);

$lgaId = '';
if (isset($_GET['lga_id'])) { $lgaId = $_GET['lga_id']; }
$lgaName = '';
$calculatedTotals = [];
$announcedTotals = [];
$comparisonRows = [];

if ($lgaId) {
    $statement = mysqli_prepare($conn, 'SELECT party_abbreviation, SUM(party_score) AS total_score
                                        FROM announced_pu_results
                                        WHERE polling_unit_uniqueid IN
                                              (SELECT uniqueid FROM polling_unit WHERE lga_id = ?)
                                        GROUP BY party_abbreviation
                                        ORDER BY total_score DESC');
    mysqli_stmt_bind_param($statement, 'i', $lgaId);
    mysqli_stmt_execute($statement);
    $calculatedTotals = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);

    $statement = mysqli_prepare($conn, 'SELECT uniqueid, lga_name FROM lga WHERE lga_id = ?');
    mysqli_stmt_bind_param($statement, 'i', $lgaId);
    mysqli_stmt_execute($statement);
    $lgaRow = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    $lgaUniqueId = 0;
    $lgaName = '';
    if (isset($lgaRow['uniqueid'])) { $lgaUniqueId = $lgaRow['uniqueid']; }
    if (isset($lgaRow['lga_name'])) { $lgaName = $lgaRow['lga_name']; }

    $statement = mysqli_prepare($conn, 'SELECT party_abbreviation, party_score
                                        FROM announced_lga_results WHERE lga_name = ?');
    mysqli_stmt_bind_param($statement, 'i', $lgaUniqueId);
    mysqli_stmt_execute($statement);
    $announcedRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    foreach ($announcedRows as $announcedRow) {
        $announcedTotals[$announcedRow['party_abbreviation']] = (int)$announcedRow['party_score'];
    }

    $calculatedTotalsByParty = [];
    foreach ($calculatedTotals as $calculatedTotal) {
        $calculatedTotalsByParty[$calculatedTotal['party_abbreviation']] = (int)$calculatedTotal['total_score'];
    }
    $allParties = array_unique(array_merge(array_keys($calculatedTotalsByParty), array_keys($announcedTotals)));
    foreach ($allParties as $party) {
        $calculatedScore = null;
        $announcedScore = null;
        if (isset($calculatedTotalsByParty[$party])) { $calculatedScore = $calculatedTotalsByParty[$party]; }
        if (isset($announcedTotals[$party])) { $announcedScore = $announcedTotals[$party]; }
        $comparisonRows[] = [
            'party' => $party,
            'calculated' => $calculatedScore,
            'announced' => $announcedScore,
            'difference' => null,
        ];
        if ($calculatedScore !== null && $announcedScore !== null) {
            $comparisonRows[count($comparisonRows) - 1]['difference'] = $calculatedScore - $announcedScore;
        }
    }
    usort($comparisonRows, function ($firstRow, $secondRow) {
        $secondScore = -1;
        $firstScore = -1;
        if ($secondRow['calculated'] !== null) { $secondScore = $secondRow['calculated']; }
        if ($firstRow['calculated'] !== null) { $firstScore = $firstRow['calculated']; }
        return $secondScore <=> $firstScore;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LGA Total Votes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width: 640px;"><nav class="mb-3"><a href="index.php">&larr; Home</a></nav>
<h1>LGA Total Votes</h1>
<form method="get">
    <label>Local Government</label>
    <select class="form-select d-inline-block w-auto" name="lga_id" onchange="this.form.submit()" required>
        <option value="">-- select LGA --</option>
        <?php foreach ($lgaList as $lga): ?>
        <option value="<?= $lga['lga_id'] ?>" <?php if ($lga['lga_id'] == $lgaId) { ?>selected<?php } ?> ><?= $lga['lga_name'] ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn-outline-secondary">Go</button></noscript>
</form>

<?php if ($lgaId && $lgaName): ?>
<h2><?= $lgaName ?></h2>
<?php if ($comparisonRows): ?>
<p>Total votes from all polling units under this LGA, compared with the result announced at the LGA coalition centre:</p>
<table class="table table-striped table-bordered">
    <tr><th>Party</th><th>Calculated From Polling Units</th><th>Announced At LGA</th><th>Difference</th></tr>
    <?php foreach ($comparisonRows as $comparisonRow): ?>
    <tr>
        <td><?= $comparisonRow['party'] ?></td>
        <td><?php if ($comparisonRow['calculated'] === null) { ?>Not recorded<?php } else { ?><?= $comparisonRow['calculated'] ?><?php } ?></td>
        <td><?php if ($comparisonRow['announced'] === null) { ?>Not announced<?php } else { ?><?= $comparisonRow['announced'] ?><?php } ?></td>
        <td><?php if ($comparisonRow['difference'] === null) { ?>-<?php } else { ?><?= $comparisonRow['difference'] ?><?php } ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p class="alert alert-danger">No results have been recorded for this LGA, either from its polling units or at the coalition centre.</p>
<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
