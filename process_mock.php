<?php
require_once 'auth.php';
requireLogin();

require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Color\Color;

if (!isset($_GET['file'])) {
    die("No file specified.");
}

$filename = basename($_GET['file']);

if (strpos($filename, 'processed_') === 0) {
    $filepath = PROCESSED_DIR . $filename;
} else {
    $filepath = UPLOAD_DIR . $filename;
}

if (!file_exists($filepath)) {
    die("File not found.");
}

// Mock mode is configurable via query parameter (default: true)
$mockModeParam = isset($_GET['mock_mode']) ? $_GET['mock_mode'] : '1';
define('MOCK_MODE', $mockModeParam !== '0');

// QR code position on slides (default: bottom-right)
$qrPosition = isset($_GET['qr_pos']) ? $_GET['qr_pos'] : 'bottom-right';
$allowedPositions = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
if (!in_array($qrPosition, $allowedPositions)) {
    $qrPosition = 'bottom-right';
}

$presentationId = isset($_GET['pid']) ? intval($_GET['pid']) : null;

if (!MOCK_MODE) {
    $client = \OpenAI::client(OPENAI_API_KEY);
} else {
    $client = null;
}

$tempDir = sys_get_temp_dir() . '/pptx_' . uniqid() . '/';
mkdir($tempDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($filepath) !== TRUE) {
    die("Failed to open presentation");
}
$zip->extractTo($tempDir);
$zip->close();

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
//$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$baseUrl = PUBLIC_BASE_URL;

$slidesDir = $tempDir . 'ppt/slides/';
$slideFiles = glob($slidesDir . 'slide*.xml');
$slideCount = count($slideFiles);
$processedSlides = 0;
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset='UTF-8'>
    <title>Processing...</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="container process-page">
        <h1>Processing Presentation</h1>
        <?php
        if (MOCK_MODE) {
            echo "<span class='mock-badge'>MOCK MODE - Using Test Data (No API Costs)</span><br>";
        }
        ?>
        <div id='progress'>
            <?php
            $apiCallCount = 0;

            foreach ($slideFiles as $index => $slideFile) {
                $slideNum = $index + 1;
                echo "<div class='progress'>";
                echo "<strong>Processing slide $slideNum...</strong><br>";
                flush();

                $slideXml = file_get_contents($slideFile);

                if (slideHasQRCode($slideXml)) {
                    echo "Slide already contains a QR code &mdash; <strong>skipping</strong><br>";
                    echo "</div><hr>";
                    flush();
                    continue;
                }

                $slideAnalysis = analyzeSlideXml($slideXml, $tempDir, $slideFile);

                echo "Slide type: <strong>{$slideAnalysis['type']}</strong><br>";
                flush();

                $qrImagePath = null;
                $viewUrl = null;

                if ($slideAnalysis['type'] === 'text_only' && !empty($slideAnalysis['text'])) {
                    echo "Text content: " . substr($slideAnalysis['text'], 0, 100) . "...<br>";
                    echo "Generating " . (MOCK_MODE ? "mock" : "AI") . " image from text...<br>";
                    flush();

                    if (!MOCK_MODE && $apiCallCount > 0) {
                        echo "<span class='warning'>⏳ Waiting " . API_RATE_LIMIT_SLEEP . " seconds to avoid rate limits...</span><br>";
                        flush();
                        sleep(API_RATE_LIMIT_SLEEP);
                    }

                    $mockImagePath = generateImageFromText($client, $slideAnalysis['text']);
                    if (!MOCK_MODE) $apiCallCount++;

                    if ($mockImagePath && file_exists($mockImagePath)) {
                        $aiImagePath = $mockImagePath;

                        $viewUrl = $baseUrl . '/view_content.php?type=image&file=' . urlencode(basename($aiImagePath))
                                 . ($presentationId ? '&pid=' . $presentationId . '&slide=' . $slideNum : '');
                        $qrImagePath = generateQRCode($viewUrl, $filename . '_slide_' . $slideNum . '_img_qr');

                        echo "<span class='success'>✓ " . (MOCK_MODE ? "Mock" : "AI") . " image generated successfully!</span><br>";
                        echo "QR code will link to: <a href=$viewUrl>image</a><br>";
                        
                    } else {
                        echo "<span class='error'>✗ Failed to generate image</span><br>";
                    }
                    flush();
                } elseif ($slideAnalysis['type'] === 'image_only') {
                    echo "Image-only slide detected<br>";
                    echo "Extracting image from slide...<br>";
                    flush();

                    $extractedImagePath = extractImageFromSlide($tempDir, $slideFile, $slideNum, $filename);

                    if ($extractedImagePath && file_exists($extractedImagePath)) {
                        echo "Image extracted: " . basename($extractedImagePath) . "<br>";
                        echo "Generating " . (MOCK_MODE ? "mock" : "AI") . " description of image...<br>";
                        flush();

                        if (!MOCK_MODE && $apiCallCount > 0) {
                            echo "<span class='warning'>⏳ Waiting " . API_RATE_LIMIT_SLEEP . " seconds to avoid rate limits...</span><br>";
                            flush();
                            sleep(API_RATE_LIMIT_SLEEP);
                        }

                        $description = generateTextFromImage($client, $extractedImagePath);
                        if (!MOCK_MODE) $apiCallCount++;

                        if ($description) {
                            $textPath = AI_TEXTS_DIR . $filename . '_slide_' . $slideNum . '.txt';
                            file_put_contents($textPath, $description);

                            $viewUrl = $baseUrl . '/view_content.php?type=text&file=' . urlencode(basename($textPath))
                                     . ($presentationId ? '&pid=' . $presentationId . '&slide=' . $slideNum : '');
                            $qrImagePath = generateQRCode($viewUrl, $filename . '_slide_' . $slideNum . '_text_qr');

                            echo "<span class='success'>✓ " . (MOCK_MODE ? "Mock" : "AI") . " description generated successfully!</span><br>";
                            echo "Description preview: " . substr($description, 0, 150) . "...<br>";
                            echo "QR code will link to: <a href=$viewUrl>description</a><br>";
                        } else {
                            echo "<span class='error'>✗ Failed to generate description</span><br>";
                        }
                    } else {
                        echo "<span class='error'>✗ Failed to extract image from slide</span><br>";
                    }
                    flush();
                }

                if ($qrImagePath && file_exists($qrImagePath)) {
                    echo "Adding QR code to slide...<br>";
                    flush();

                    if (addQRToSlideXML($slideFile, $qrImagePath, $tempDir, $slideNum, $viewUrl, $qrPosition)) {
                        $processedSlides++;
                        echo "<span class='success'>✓ QR code successfully added to slide $slideNum (" . htmlspecialchars($qrPosition) . ")</span><br>";

                        if ($presentationId) {
                            try {
                                $db = getDB();
                                $sql = "INSERT INTO qr_codes (presentation_id, slide_number, qr_image_path, target_url)
                            VALUES (:pid, :slide, :path, :url)";
                                $stmt = $db->prepare($sql);
                                $stmt->execute([
                                    ':pid' => $presentationId,
                                    ':slide' => $slideNum,
                                    ':path' => $qrImagePath,
                                    ':url' => $viewUrl,
                                ]);
                            } catch (Exception $e) {
                                error_log("Error saving QR code to DB: " . $e->getMessage());
                            }
                        }
                    } else {
                        echo "<span class='error'>✗ Failed to add QR code to slide $slideNum</span><br>";
                    }
                    flush();
                }

                echo "</div><hr>";
            }

            flush();

            $processedFilename = PROCESSED_DIR . 'processed_' . $filename;
            $newZip = new ZipArchive();
            if ($newZip->open($processedFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                die("Failed to create new presentation");
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($tempDir));
                    $newZip->addFile($filePath, $relativePath);
                }
            }

            $newZip->close();

            deleteDirectory($tempDir);
            ?>
        </div>
        <h2>✓ Processing Complete!</h2>
        <?php
        if (MOCK_MODE) {
            echo "<p class='info process-page'>This presentation was processed in <strong>MOCK MODE</strong>. To use real AI generation, set MOCK_MODE to false in the code and add OpenAI API credits.</p>";
        }

        echo "<p><strong>Total slides:</strong> $slideCount</p>";
        echo "<p><strong>Slides enhanced with QR codes:</strong> $processedSlides</p>";

        if (!MOCK_MODE) {
            echo "<p><strong>Total API calls made:</strong> $apiCallCount</p>";
        }
        echo "
        <p>
            <a href='download.php?file=" . urlencode(basename($processedFilename)) . "&type=processed' class='btn-action download'>Download Enhanced Presentation</a>
            <a href='dashboard.php' class='btn-action dashboard'>Go to Dashboard</a>
        </p>";
        ?>
        <p><a href='index.php' class='btn-action dashboard'>← Upload another presentation</a></p>
    </div>
</body>

</html>
<?php
function analyzeSlideXml($slideXml, $tempDir, $slideFile)
{
    $hasText = false;
    $hasImage = false;
    $textContent = '';

    if (preg_match_all('/<a:t>([^<]+)<\/a:t>/', $slideXml, $matches)) {
        foreach ($matches[1] as $text) {
            $text = trim($text);
            if (!empty($text)) {
                $hasText = true;
                $textContent .= $text . ' ';
            }
        }
    }

    if (preg_match('/<p:pic>/', $slideXml) || preg_match('/<a:blip/', $slideXml)) {
        $hasImage = true;
    }

    $textContent = trim($textContent);

    if ($hasText && !$hasImage && !empty($textContent)) {
        return ['type' => 'text_only', 'text' => $textContent];
    } elseif ($hasImage && !$hasText) {
        return ['type' => 'image_only'];
    } else {
        return ['type' => 'mixed', 'text' => $textContent];
    }
}

function extractImageFromSlide($tempDir, $slideFile, $slideNum, $filename)
{
    try {
        $slideXml = file_get_contents($slideFile);

        if (preg_match('/<a:blip[^>]+r:embed="([^"]+)"/', $slideXml, $matches)) {
            $rId = $matches[1];

            $relsFile = str_replace('/slides/slide', '/slides/_rels/slide', $slideFile) . '.rels';

            if (file_exists($relsFile)) {
                $relsXml = file_get_contents($relsFile);

                if (preg_match('/<Relationship[^>]+Id="' . preg_quote($rId, '/') . '"[^>]+Target="([^"]+)"/', $relsXml, $targetMatch)) {
                    $imagePath = $targetMatch[1];

                    $imagePath = str_replace('../', '', $imagePath);
                    $fullImagePath = $tempDir . 'ppt/' . $imagePath;

                    if (file_exists($fullImagePath)) {
                        $originalExt = pathinfo($fullImagePath, PATHINFO_EXTENSION);

                        $extractedPath = TEMP_IMAGES_DIR . $filename . '_slide_' . $slideNum . '_extracted.' . $originalExt;
                        copy($fullImagePath, $extractedPath);

                        if (in_array(strtolower($originalExt), ['jpg', 'jpeg'])) {
                            $pngPath = TEMP_IMAGES_DIR . $filename . '_slide_' . $slideNum . '_extracted.png';

                            $image = imagecreatefromjpeg($extractedPath);
                            if ($image) {
                                imagepng($image, $pngPath);
                                imagedestroy($image);
                                return $pngPath;
                            }
                        }

                        return $extractedPath;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Image extraction error: " . $e->getMessage());
    }

    return null;
}

function generateImageFromText($client, $text)
{
    if (MOCK_MODE) {
        try {
            echo "Using MOCK image generation (no API call)...<br>";
            flush();

            $width = 1024;
            $height = 1024;
            $image = imagecreatetruecolor($width, $height);

            for ($y = 0; $y < $height; $y++) {
                $r = (int)(100 + ($y / $height) * 155);
                $g = (int)(150 + ($y / $height) * 105);
                $b = (int)(200 + ($y / $height) * 55);
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, 0, $y, $width, $y, $color);
            }

            $white = imagecolorallocate($image, 255, 255, 255);

            imagerectangle($image, 50, 50, $width - 50, $height - 50, $white);
            imagerectangle($image, 52, 52, $width - 52, $height - 52, $white);

            $title = "AI Generated Image";
            $fontSize = 5;
            $textWidth = imagefontwidth($fontSize) * strlen($title);
            $x = ($width - $textWidth) / 2;
            imagestring($image, $fontSize, $x, 100, $title, $white);

            $maxChars = 40;
            $textLines = wordwrap(substr($text, 0, 200), $maxChars, "\n", true);
            $lines = explode("\n", $textLines);

            $y = 200;
            foreach ($lines as $line) {
                $lineWidth = imagefontwidth(3) * strlen($line);
                $lineX = ($width - $lineWidth) / 2;
                imagestring($image, 3, $lineX, $y, $line, $white);
                $y += 30;
            }

            imagestring($image, 5, 400, 900, "MOCK DATA - Testing Mode", $white);

            $mockImagePath = AI_IMAGES_DIR . 'mock_' . uniqid() . '.png';
            imagepng($image, $mockImagePath);
            imagedestroy($image);

            return $mockImagePath;
        } catch (Exception $e) {
            echo "<span class='error'>Mock Image Error: " . $e->getMessage() . "</span><br>";
            return null;
        }
    } else {
        try {
            $cleanText = substr($text, 0, 900);
            $prompt = "Create a professional, high-quality, visually appealing image that represents this content: " . $cleanText;

            echo "Sending request to DALL-E 3...<br>";
            flush();

            $response = $client->images()->create([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'standard',
            ]);

            if (isset($response->data[0]->url)) {
                $imageUrl = $response->data[0]->url;
                $aiImagePath = AI_IMAGES_DIR . 'ai_' . uniqid() . '.png';
                file_put_contents($aiImagePath, file_get_contents($imageUrl));
                return $aiImagePath;
            }

            return null;
        } catch (Exception $e) {
            echo "<span class='error'>DALL-E Error: " . $e->getMessage() . "</span><br>";
            return null;
        }
    }
}

function generateTextFromImage($client, $imagePath)
{
    if (MOCK_MODE) {
        try {
            if (!file_exists($imagePath)) {
                return null;
            }

            echo "Using MOCK text generation (no API call)...<br>";
            flush();

            $imageInfo = getimagesize($imagePath);
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo['mime'];

            $description = "MOCK AI-GENERATED DESCRIPTION (Testing Mode)\n\n";
            $description .= "Image Analysis:\n\n";

            $description .= "Technical Details:\n";
            $description .= "• Dimensions: {$width} x {$height} pixels\n";
            $description .= "• Format: {$type}\n";
            $description .= "• File: " . basename($imagePath) . "\n\n";

            $description .= "Visual Description:\n";
            $description .= "This image appears to be part of a presentation slide. ";
            $description .= "The composition includes various visual elements arranged to convey information effectively. ";
            $description .= "The color palette is professional and suitable for business or educational contexts.\n\n";

            $description .= "Key Elements:\n";
            $description .= "• The image likely contains charts, diagrams, or photographic content\n";
            $description .= "• Visual hierarchy guides the viewer's attention\n";
            $description .= "• The layout is designed for clarity and impact\n\n";

            $description .= "Overall Theme:\n";
            $description .= "This slide image serves to support and enhance the presentation narrative, ";
            $description .= "providing visual reinforcement of key concepts or data points being discussed.\n\n";

            $description .= "Note: This is a mock description for testing. ";
            $description .= "In production with real API, this would contain detailed AI-powered analysis.";

            return $description;
        } catch (Exception $e) {
            echo "<span class='error'>Mock Text Error: " . $e->getMessage() . "</span><br>";
            return null;
        }
    } else {
        try {
            if (!file_exists($imagePath)) {
                return null;
            }

            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            echo "Sending request to GPT-4o with vision...<br>";
            flush();

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Describe this image in detail. Provide a comprehensive, professional description.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}",
                                    'detail' => 'high'
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 800
            ]);

            if (isset($response->choices[0]->message->content)) {
                return $response->choices[0]->message->content;
            }

            return null;
        } catch (Exception $e) {
            echo "<span class='error'>GPT-4 Vision Error: " . $e->getMessage() . "</span><br>";
            return null;
        }
    }
}

function generateQRCode($url, $filename)
{
    $qrCode = new QrCode(
        data: $url,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 400,
        margin: 10,
        roundBlockSizeMode: RoundBlockSizeMode::Margin,
        foregroundColor: new Color(0, 0, 0),
        backgroundColor: new Color(255, 255, 255)
    );

    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    $qrPath = QR_DIR . $filename . '.png';
    $result->saveToFile($qrPath);

    return $qrPath;
}

function addQRToSlideXML($slideFile, $qrImagePath, $tempDir, $slideNum, $viewUrl = '', $position = 'bottom-right')
{
    try {
        $mediaDir = $tempDir . 'ppt/media/';
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        $imageExt = pathinfo($qrImagePath, PATHINFO_EXTENSION);
        $newImageName = 'qrcode_slide' . $slideNum . '.' . $imageExt;
        $newImagePath = $mediaDir . $newImageName;
        copy($qrImagePath, $newImagePath);

        $slideXml = file_get_contents($slideFile);
        $rId = 'rIdQR' . $slideNum;
        $rIdHlink = 'rIdQRHlink' . $slideNum;

        $qrSize = 1143000;
        $slideWidth = 9144000;
        $slideHeight = 6858000;
        $margin = 228600;

        switch ($position) {
            case 'top-left':
                $xPos = $margin;
                $yPos = $margin;
                break;
            case 'top-right':
                $xPos = $slideWidth - $qrSize - $margin;
                $yPos = $margin;
                break;
            case 'bottom-left':
                $xPos = $margin;
                $yPos = $slideHeight - $qrSize - $margin;
                break;
            case 'bottom-right':
            default:
                $xPos = $slideWidth - $qrSize - $margin;
                $yPos = $slideHeight - $qrSize - $margin;
                break;
        }

        $hlinkXml = '';
        if (!empty($viewUrl)) {
            $hlinkXml = '<a:hlinkClick r:id="' . $rIdHlink . '"/>';
        }

        $picXml = '<p:sp>
<p:nvSpPr>
<p:cNvPr id="999' . $slideNum . '" name="QR Code ' . $slideNum . '">' . $hlinkXml . '</p:cNvPr>
<p:cNvSpPr>
<a:spLocks noGrp="1"/>
</p:cNvSpPr>
<p:nvPr/>
</p:nvSpPr>
<p:spPr>
<a:xfrm>
<a:off x="' . $xPos . '" y="' . $yPos . '"/>
<a:ext cx="' . $qrSize . '" cy="' . $qrSize . '"/>
</a:xfrm>
<a:prstGeom prst="rect">
<a:avLst/>
</a:prstGeom>
<a:blipFill>
<a:blip r:embed="' . $rId . '"/>
<a:stretch>
<a:fillRect/>
</a:stretch>
</a:blipFill>
</p:spPr>
<p:txBody>
<a:bodyPr/>
<a:lstStyle/>
<a:p/>
</p:txBody>
</p:sp>';
        $slideXml = str_replace('</p:spTree>', $picXml . '</p:spTree>', $slideXml);
        file_put_contents($slideFile, $slideXml);

        $relsFile = str_replace('/slides/slide', '/slides/_rels/slide', $slideFile) . '.rels';

        $hlinkRel = '';
        if (!empty($viewUrl)) {
            $hlinkRel = '    <Relationship Id="' . $rIdHlink . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="' . htmlspecialchars($viewUrl, ENT_XML1) . '" TargetMode="External"/>';
        }

        if (!file_exists($relsFile)) {
            $relsDir = dirname($relsFile);
            if (!is_dir($relsDir)) {
                mkdir($relsDir, 0755, true);
            }

            $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $newImageName . '"/>
' . $hlinkRel . '
</Relationships>';
        } else {
            $relsXml = file_get_contents($relsFile);
            $newRel = '<Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $newImageName . '"/>';
            if (!empty($hlinkRel)) {
                $newRel .= "\n" . $hlinkRel;
            }
            $relsXml = str_replace('</Relationships>', $newRel . '</Relationships>', $relsXml);
        }
        file_put_contents($relsFile, $relsXml);

        $contentTypesFile = $tempDir . '[Content_Types].xml';
        if (file_exists($contentTypesFile)) {
            $contentTypesXml = file_get_contents($contentTypesFile);

            if (strpos($contentTypesXml, 'Extension="png"') === false) {
                $pngDefault = '<Default Extension="png" ContentType="image/png"/>';
                $contentTypesXml = str_replace('</Types>', $pngDefault . '</Types>', $contentTypesXml);
                file_put_contents($contentTypesFile, $contentTypesXml);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Error adding QR to slide XML: " . $e->getMessage());
        return false;
    }
}
function slideHasQRCode($slideXml)
{
    if (preg_match('/name="QR Code/', $slideXml)) {
        return true;
    }
    if (preg_match('/qrcode_slide/', $slideXml)) {
        return true;
    }
    return false;
}

function deleteDirectory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}
?>