<?php

set_time_limit(getenv('MAX_EXECUTION_TIME') ?? 300);

function generateTempFile($content = null, $ext = 'html')
{
    $tmpName = tempnam(sys_get_temp_dir(), $ext) . '.' . $ext;
    if ($content)
        file_put_contents($tmpName, $content);

    return $tmpName;
}

header('Content-Type: application/json');

// Accept only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

$procNum = rand(1000, 9999);
$html = $input['html'] ?? null;
$url = $input['url'] ?? null;
$params = $input['params'] ?? [];
$files = $input['files'] ?? [];

$uri = strtolower(trim($_SERVER['REQUEST_URI'], '/'));
$format = in_array($uri, ['png', 'jpg', 'jpeg', 'tiff']) ? $uri : 'pdf';

if (!$html && !$url) {
    http_response_code(400);
    echo json_encode(['error' => 'Either "html" or "url" must be provided']);
    exit;
}

$tmpFile = [];
$tmpFile['output'] = generateTempFile(null, $format);

if ($html) {
    $tmpFile['html'] = generateTempFile($html);
    copy($tmpFile['html'], "./tmp/{$procNum}.html");
}

try {
    $cmd = [
        $format === 'pdf' ? 'wkhtmltopdf' : 'wkhtmltoimage',
        '--quiet',
    ];

    error_log(date('[H:i:s] ') . "{$procNum}- New request: format={$format} url={$url} html=" . strlen($html) . " bytes");

    // set default params
    $params['encoding'] ??= 'utf-8';

    foreach ($params as $param => $value) {
        // TODO: reject invalid or unnecessary params  
        // Store Header / Footer in a temp file
        if (in_array($param, ['header-html', 'footer-html']))
            $value = $tmpFile[$param] = generateTempFile($value);

        $param = trim($param, ' -');
        if (preg_match('/^[-a-z0-9]+$/i', $param)) {
            $cmd[] = '--' . $param;
            $cmd[] = $value;
        }
    }


    // store files to a temp folder and set --allow param for it
    if ($files) {
        $tmpFolder = tempnam(sys_get_temp_dir(), 'embed');
        mkdir($tmpFolder);

        $cmd[] = '--allow';
        $cmd[] = $tmpFolder;

        foreach ($files as $name => $blob) {
            $name = trim(str_replace('..', '', $name), '/. ');
            $name = $tmpFolder . '/' . $name;
            if (file_exists($name))
                continue;

            mkdir(dirname($name), 0777, true);
            file_put_contents($name, $blob);
            $tmpFile[$name] = $name;
        }
    }

    // Input & Output
    $cmd[] = $tmpFile['html'] ?? $url; // input file / url
    $cmd[] = $tmpFile['output'];   // output file

    // Open process
    $descriptors = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w'], // stderr
    ];

    error_log(date('[H:i:s] ') . "{$procNum}- Process started");
    if ($input['debug'] ?? false)
        file_put_contents('doc.sh', implode(' ', $cmd));
    $process = proc_open($cmd, $descriptors, $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Could not open wkhtmltopdf process');
    }

    // Capture error (if any)
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    // Wait for process to finish
    $status = proc_close($process);

    if ($status !== 0) {
        throw new RuntimeException("PDF generation failed: $stderr");
    }

    // Serve generated PDF / Image
    if ($format === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="output.pdf"');
    } else {
        header('Content-Type: image/' . $format);
        header('Content-Disposition: inline; filename="output.' . $format . '"');
    }
    error_log(date('[H:i:s] ') . "{$procNum}- Process ended");
    readfile($tmpFile['output']);
    error_log(date('[H:i:s] ') . "{$procNum}- Output sent");
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'cmd' => implode(' ', $cmd),
    ]);
    error_log(date('[H:i:s] ') . "{$procNum}- ERROR " . $e->getMessage());
} finally {
    // cleanup
    foreach ($tmpFile as $tmp)
        unlink($tmp);

    if ($tmpFolder ?? false)
        shell_exec("rm -rf $tmpFolder");
}

error_log(date('[H:i:s] ') . "{$procNum}- Finished");
