<?php
require_once __DIR__ . '/../models/Lead.php';
require_once __DIR__ . '/../models/Message.php';

$leadModel = new Lead();
$board = $leadModel->getPipelineBoard();
echo "SUCCESS: Database connected!\n";
echo "Total Stages: " . count($board) . "\n";
foreach ($board as $col) {
    echo " - " . $col['stage_name'] . ": " . count($col['leads']) . " leads\n";
}
