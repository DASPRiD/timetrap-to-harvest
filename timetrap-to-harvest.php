#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$configPath = str_replace('~', posix_getpwuid(posix_getuid())['dir'], '~/.timetrap-to-harvest.json');

if (!file_exists($configPath)) {
    echo "Config file not found\n";
    exit(1);
}

$configReader = new Zend\Config\Reader\Json();
$config       = $configReader->fromFile($configPath);

$dbPath = str_replace('~', posix_getpwuid(posix_getuid())['dir'], $config['db']);

$db = new Zend\Db\Adapter\Adapter([
    'driver'   => 'Pdo_Sqlite',
    'database' => $dbPath,
]);

if ($argc === 1) {
    $date = new DateTimeImmutable();
} else {
    $date = new DateTimeImmutable(trim($argv[1]));
}

$entries = $db->query('SELECT * FROM entries WHERE ? BETWEEN DATE(start) AND DATE(end) ORDER BY start', [
    $date->format('Y-m-d')
])->toArray();

$console = Zend\Console\Console::getInstance();

$api = new Harvest\HarvestAPI();
$api->setUser($config['email']);
$api->setPassword($config['password']);
$api->setAccount($config['account']);
$api->setSSL(true);

echo "Transfering times to Harvest…\n";
$console->writeLine(str_repeat('-', 79), Zend\Console\ColorInterface::YELLOW);

$minTime = $date->setTime(0, 0, 0);
$maxTime = $date->setTime(23, 59, 59);

foreach ($entries as $entry) {
    if (!isset($config['sheets'][$entry['sheet']])) {
        continue;
    }

    $sheetData = $config['sheets'][$entry['sheet']];

    $start = new DateTime($entry['start']);
    $end   = new DateTime($entry['end']);

    if ($start < $minTime) {
        $start = clone $minTime;
    }

    if ($end > $maxTime) {
        $end = clone $maxTime;
    }

    list($hours, $minutes, $seconds) = explode(':', $start->diff($end)->format('%H:%I:%S'));

    if ($hours == 0 && $minutes < 15) {
        $minutes = 15;
    } else {
        $x = floor($minutes / 15) * 15;
        $y = $minutes % 15;

        if ($y >= 5) {
            $x += 15;
        }

        $minutes = $x;

        if ($minutes > 59) {
            $minutes = 0;
            $hours++;
        }
    }

    printf("%s - %s\n", sprintf('%dh %dm', $hours, $minutes), $entry['note']);

    $harvestEntry = new Harvest\Model\DayEntry();
    $harvestEntry->set('notes', $entry['note']);
    $harvestEntry->set('hours', $hours + $minutes / 60);
    $harvestEntry->set('project_id', $sheetData['projectId']);
    $harvestEntry->set('task_id', $sheetData['taskId']);
    $harvestEntry->set('spent_at', $start->format('D, j M Y'));

    if ($api->createEntry($harvestEntry)->isSuccess()) {
        $console->writeLine('Time transfered', Zend\Console\ColorInterface::GREEN);
    } else {
        $console->writeLine('Time not transfered', Zend\Console\ColorInterface::RED);
    }

    $console->writeLine(str_repeat('-', 79), Zend\Console\ColorInterface::YELLOW);
}
