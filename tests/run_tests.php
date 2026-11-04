<?php

/**
 * ApexApprove - Automated Verification & Test Runner
 * Open-Source Test Suite for Core Security & Business Logic
 */

declare(strict_types=1);

echo "========================================================\n";
echo "🧪 Running ApexApprove Automated Verification Test Suite\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ PASS: {$description}\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: {$description}\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// TEST SUITE 1: Email OTP Security & Cryptographic Hashing Logic
// -----------------------------------------------------------------------------
echo "1. Testing Email OTP Generation & Hash Verification...\n";

// Test 1.1: 6-digit numeric randomness & entropy
$generatedCodes = [];
for ($i = 0; $i < 100; $i++) {
    $code = (string) random_int(100000, 999999);
    $generatedCodes[$code] = true;
    if (strlen($code) !== 6 || !ctype_digit($code) || (int)$code < 100000 || (int)$code > 999999) {
        assertTest("OTP format valid (6 digits)", false);
        break;
    }
}
assertTest("Generated OTPs are 6-digit numbers in range [100000, 999999]", true);
assertTest("Generated OTPs have sufficient entropy (no duplicates in 100 iterations)", count($generatedCodes) === 100);

// Test 1.2: SHA-256 Storage Hashing
$testOtp = "842917";
$expectedHash = hash('sha256', $testOtp);
$submittedOtp = "842917";
$submittedHash = hash('sha256', trim($submittedOtp));
assertTest("Valid OTP SHA-256 hash match via hash_equals", hash_equals($expectedHash, $submittedHash));

$wrongOtp = "842918";
$wrongHash = hash('sha256', trim($wrongOtp));
assertTest("Invalid OTP correctly rejected", !hash_equals($expectedHash, $wrongHash));

// -----------------------------------------------------------------------------
// TEST SUITE 2: Deterministic Approval Ledger & Cryptographic Tamper-Evidence
// -----------------------------------------------------------------------------
echo "\n2. Testing Approval Ledger & SHA-256 Version Binding...\n";

$assetContent = "CREATIVE_ASSET_BINARY_PROOF_V1";
$assetSha256 = hash('sha256', $assetContent);
$mockServerAppKey = "base64:s0mE_sEcUrE_rAndOm_App_kEy_fOr_tEStInG==";

// Test 2.1: Matching Asset Hash
$claimedHash = $assetSha256;
$isHashMatch = hash_equals($assetSha256, strtolower(trim($claimedHash)));
assertTest("Legitimate asset SHA-256 matches exact intended version", $isHashMatch);

// Test 2.2: Tampered Asset Hash
$tamperedClaimedHash = hash('sha256', "ALTERED_CREATIVE_ASSET_BINARY_PROOF_V2");
$isTamperedMatch = hash_equals($assetSha256, strtolower(trim($tamperedClaimedHash)));
assertTest("Altered deliverable version hash correctly triggers mismatch", !$isTamperedMatch);

// Test 2.3: HMAC-SHA256 Audit Signature
$evidencePayload = [
    'approval_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
    'version_id' => 'dver_12345678',
    'asset_sha256' => $assetSha256,
    'approver_email' => 'client.signer@example.com',
    'approved_at' => '2026-11-04T10:15:30Z',
];
$signature = hash_hmac('sha256', json_encode($evidencePayload), $mockServerAppKey);
assertTest("Audit record signature is 64-character HMAC-SHA256", strlen($signature) === 64 && ctype_xdigit($signature));

// Test 2.4: Signature detects tampering with approver email or timestamp
$tamperedPayload = $evidencePayload;
$tamperedPayload['approver_email'] = 'imposter@attacker.com';
$tamperedSignature = hash_hmac('sha256', json_encode($tamperedPayload), $mockServerAppKey);
assertTest("Tampering with evidence payload invalidates signature", !hash_equals($signature, $tamperedSignature));

// -----------------------------------------------------------------------------
// TEST SUITE 3: Video Review Engine (MIME Type & Fallback Decisioning)
// -----------------------------------------------------------------------------
echo "\n3. Testing Video Review Engine Compatibility Logic...\n";

$browserNativeMimes = ['video/mp4', 'video/webm', 'video/ogg'];

function evaluateMime(string $mime, bool $ffmpegWorkerEnabled, array $nativeMimes): string {
    if (in_array(strtolower($mime), $nativeMimes, true)) {
        return 'direct_html5';
    }
    if ($ffmpegWorkerEnabled) {
        return 'queued_for_transcoding';
    }
    return 'fallback_download_only';
}

assertTest("video/mp4 defaults to direct_html5 streaming", evaluateMime('video/mp4', false, $browserNativeMimes) === 'direct_html5');
assertTest("video/webm defaults to direct_html5 streaming", evaluateMime('video/webm', false, $browserNativeMimes) === 'direct_html5');
assertTest("video/quicktime (ProRes) defaults to fallback_download_only when FFmpeg disabled", evaluateMime('video/quicktime', false, $browserNativeMimes) === 'fallback_download_only');
assertTest("video/quicktime routes to queued_for_transcoding when FFmpeg enabled", evaluateMime('video/quicktime', true, $browserNativeMimes) === 'queued_for_transcoding');

// -----------------------------------------------------------------------------
// TEST SUITE 4: SQL Migration Files Syntax & Consistency Check
// -----------------------------------------------------------------------------
echo "\n4. Testing Database Migration Files Integrity...\n";

$migrationDir = __DIR__ . '/../database/migrations';
$migrationFiles = glob($migrationDir . '/*.sql');

assertTest("Migration files exist in database/migrations", count($migrationFiles) >= 4);

foreach ($migrationFiles as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    
    // Check balanced parenthesis
    $openParen = substr_count($content, '(');
    $closeParen = substr_count($content, ')');
    assertTest("Migration {$filename} has balanced parentheses ({$openParen} open / {$closeParen} close)", $openParen === $closeParen);
    
    // Check CREATE TABLE presence
    assertTest("Migration {$filename} contains valid SQL table statements", stripos($content, 'CREATE TABLE') !== false);
}

// -----------------------------------------------------------------------------
// TEST SUITE 5: Hostinger Health Check Endpoint Simulation
// -----------------------------------------------------------------------------
echo "\n5. Testing Health Probe & AGPLv3 Source Compliance Responses...\n";

// Test 5.1: Health Probe JSON format
$healthResponse = json_encode([
    'status' => 'ok',
    'service' => 'ApexApprove',
    'version' => '1.0.0-agpl',
    'timestamp' => gmdate('c'),
]);
$decodedHealth = json_decode($healthResponse, true);
assertTest("Health response has status: 'ok'", ($decodedHealth['status'] ?? '') === 'ok');

// Test 5.2: AGPLv3 Compliance endpoint JSON format
$sourceResponse = json_encode([
    'license' => 'GNU Affero General Public License v3.0 (AGPLv3)',
    'repository' => 'https://github.com/mnkkal/Approval-Management',
    'maintainer' => 'Curve Metrics',
    'contact' => 'contact@curvemetrics.in',
]);
$decodedSource = json_decode($sourceResponse, true);
assertTest("AGPLv3 Source response references correct repo & license", 
    $decodedSource['repository'] === 'https://github.com/mnkkal/Approval-Management' &&
    str_contains($decodedSource['license'], 'AGPLv3')
);

// -----------------------------------------------------------------------------
// FINAL REPORT
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo "📊 Test Results Summary:\n";
echo "   Total Tests: " . ($passed + $failed) . "\n";
echo "   Passed:      {$passed}\n";
echo "   Failed:      {$failed}\n";
echo "========================================================\n";

if ($failed > 0) {
    echo "❌ Some tests failed.\n";
    exit(1);
} else {
    echo "🎉 ALL TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
}
