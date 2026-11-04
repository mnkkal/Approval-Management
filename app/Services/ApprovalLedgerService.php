<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class ApprovalLedgerService
{
    /**
     * Execute an immutable, tamper-evident approval sign-off.
     */
    public function recordApproval(
        string $versionId,
        string $approverEmail,
        string $approverName,
        string $verificationMethod,
        string $expectedSha256,
        string $confirmationStatement,
        array $clientFingerprint
    ): array {
        return DB::transaction(function () use (
            $versionId,
            $approverEmail,
            $approverName,
            $verificationMethod,
            $expectedSha256,
            $confirmationStatement,
            $clientFingerprint
        ) {
            // 1. Lock the deliverable row for update to eliminate race conditions
            $version = DB::table('deliverable_versions')
                ->join('deliverables', 'deliverables.id', '=', 'deliverable_versions.deliverable_id')
                ->where('deliverable_versions.id', $versionId)
                ->select(
                    'deliverable_versions.*',
                    'deliverables.workspace_id',
                    'deliverables.project_id',
                    'deliverables.status as deliverable_status'
                )
                ->lockForUpdate()
                ->first();

            if (!$version) {
                throw new Exception("Deliverable version not found.");
            }

            // 2. Cryptographic binding check: asset must match exact intended version hash
            if (!hash_equals($version->content_sha256, strtolower(trim($expectedSha256)))) {
                throw new Exception("Asset hash mismatch. You are approving a version that has been superseded or altered.");
            }

            // 3. Check for previous approval in forward-hash ledger
            $previousApproval = DB::table('approvals')
                ->where('workspace_id', $version->workspace_id)
                ->orderBy('created_at', 'desc')
                ->first();

            $previousHash = $previousApproval ? hash('sha256', json_encode($previousApproval)) : 'GENESIS_APPROVAL_RECORD';

            // 4. Construct immutable evidence payload
            $approvalId = Str::uuid()->toString();
            $approvedAt = Carbon::now()->toIso8601String();

            $evidencePayload = [
                'approval_id' => $approvalId,
                'version_id' => $versionId,
                'deliverable_id' => $version->deliverable_id,
                'version_number' => $version->version_number,
                'asset_sha256' => $version->content_sha256,
                'approver_email' => $approverEmail,
                'approver_name' => $approverName,
                'verification_method' => $verificationMethod,
                'confirmation_statement' => $confirmationStatement,
                'client_fingerprint' => $clientFingerprint,
                'previous_event_hash' => $previousHash,
                'approved_at' => $approvedAt,
            ];

            // 5. Generate HMAC-SHA256 signature using server app key
            $secretKey = config('app.key', 'fallback_secret_key');
            $signature = hash_hmac('sha256', json_encode($evidencePayload), $secretKey);

            // 6. Insert approval record
            DB::table('approvals')->insert([
                'id' => $approvalId,
                'version_id' => $versionId,
                'workspace_id' => $version->workspace_id,
                'project_id' => $version->project_id,
                'deliverable_id' => $version->deliverable_id,
                'version_number' => $version->version_number,
                'asset_sha256' => $version->content_sha256,
                'approver_email' => $approverEmail,
                'approver_name' => $approverName,
                'verification_method' => $verificationMethod,
                'confirmation_statement_text' => $confirmationStatement,
                'client_fingerprint' => json_encode($clientFingerprint),
                'previous_event_hash' => $previousHash,
                'record_signature' => $signature,
                'approved_at' => $approvedAt,
            ]);

            // 7. Update deliverable state to approved
            DB::table('deliverables')
                ->where('id', $version->deliverable_id)
                ->update([
                    'status' => 'approved',
                    'updated_at' => Carbon::now(),
                ]);

            // 8. Write to append-only audit log
            DB::table('audit_logs')->insert([
                'workspace_id' => $version->workspace_id,
                'event_type' => 'approval.executed',
                'actor_email' => $approverEmail,
                'ip_address_hash' => $clientFingerprint['ip_hash'] ?? 'unknown',
                'entity_type' => 'approval',
                'entity_id' => $approvalId,
                'payload' => json_encode([
                    'version_number' => $version->version_number,
                    'asset_sha256' => $version->content_sha256,
                    'signature' => $signature,
                ]),
                'created_at' => Carbon::now(),
            ]);

            return [
                'status' => 'approved',
                'approval_id' => $approvalId,
                'version_number' => $version->version_number,
                'asset_sha256' => $version->content_sha256,
                'approved_at' => $approvedAt,
                'signature' => $signature,
            ];
        });
    }
}
