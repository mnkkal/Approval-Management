import React, { useState, useRef } from 'react';

interface AnnotationPin {
  id: string;
  x: number; // Percentage (0 - 100)
  y: number; // Percentage (0 - 100)
  author: string;
  content: string;
  createdAt: string;
  isInternal: boolean;
}

interface DeliverableVersion {
  id: string;
  versionNumber: number;
  previewUrl: string;
  type: 'image' | 'pdf' | 'video';
  sha256: string;
  status: string;
}

interface ReviewProps {
  deliverable: {
    id: string;
    title: string;
    status: string;
    currentVersion: DeliverableVersion;
  };
  pins: AnnotationPin[];
  isInternalUser: boolean;
  signerEmail: string;
}

export const DeliverableReview: React.FC<ReviewProps> = ({
  deliverable,
  pins: initialPins,
  isInternalUser,
  signerEmail,
}) => {
  const [pins, setPins] = useState<AnnotationPin[]>(initialPins);
  const [activePin, setActivePin] = useState<AnnotationPin | null>(null);
  const [newPinPos, setNewPinPos] = useState<{ x: number; y: number } | null>(null);
  const [newCommentText, setNewCommentText] = useState('');
  const [isInternalComment, setIsInternalComment] = useState(false);
  const [isApprovalModalOpen, setIsApprovalModalOpen] = useState(false);
  const [declarationChecked, setDeclarationChecked] = useState(false);
  const [isSubmittingApproval, setIsSubmittingApproval] = useState(false);
  const [approvalSuccess, setApprovalSuccess] = useState(false);

  const canvasRef = useRef<HTMLDivElement>(null);

  // Handle click on canvas to place a coordinate pin
  const handleCanvasClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!canvasRef.current || deliverable.deliverable_status === 'approved') return;

    const rect = canvasRef.current.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    setNewPinPos({ x: parseFloat(x.toFixed(2)), y: parseFloat(y.toFixed(2)) });
    setActivePin(null);
  };

  // Submit comment at pin coordinate
  const handleSavePinComment = async () => {
    if (!newPinPos || !newCommentText.trim()) return;

    const createdPin: AnnotationPin = {
      id: 'pin_' + Date.now(),
      x: newPinPos.x,
      y: newPinPos.y,
      author: signerEmail,
      content: newCommentText,
      createdAt: 'Just now',
      isInternal: isInternalComment,
    };

    setPins([...pins, createdPin]);
    setNewPinPos(null);
    setNewCommentText('');
    setIsInternalComment(false);
  };

  // Execute explicit approval
  const handleConfirmApproval = async () => {
    if (!declarationChecked) return;
    setIsSubmittingApproval(true);

    try {
      const response = await fetch(`/api/v1/reviews/${deliverable.currentVersion.id}/approve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          signer_email: signerEmail,
          asset_sha256_match: deliverable.currentVersion.sha256,
          confirmation_statement_accepted: true,
        }),
      });

      if (response.ok) {
        setApprovalSuccess(true);
        setIsApprovalModalOpen(false);
      }
    } catch (err) {
      console.error('Approval execution failed', err);
    } finally {
      setIsSubmittingApproval(false);
    }
  };

  return (
    <div className="flex flex-col h-screen bg-slate-900 text-slate-100 font-sans">
      {/* Top Navigation Bar */}
      <header className="h-14 border-b border-slate-800 px-4 flex items-center justify-between bg-slate-950">
        <div className="flex items-center space-x-3">
          <span className="font-semibold text-blue-500">ApexApprove</span>
          <span className="text-slate-600">/</span>
          <span className="text-sm font-medium text-slate-300">{deliverable.title}</span>
          <span className="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-400">
            v{deliverable.currentVersion.versionNumber}
          </span>
          {deliverable.status === 'approved' || approvalSuccess ? (
            <span className="text-xs px-2.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
              Approved
            </span>
          ) : (
            <span className="text-xs px-2.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 font-medium">
              In Review
            </span>
          )}
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={() => setIsApprovalModalOpen(true)}
            disabled={deliverable.status === 'approved' || approvalSuccess}
            className="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded text-sm font-medium transition-colors shadow-sm"
          >
            {deliverable.status === 'approved' || approvalSuccess ? 'Sign-Off Complete' : 'Approve Version'}
          </button>
        </div>
      </header>

      {/* Main Review Workspace */}
      <div className="flex-1 flex overflow-hidden">
        {/* Visual Media Canvas */}
        <div className="flex-1 relative overflow-auto flex items-center justify-center p-6 bg-slate-900/50">
          <div
            ref={canvasRef}
            onClick={handleCanvasClick}
            className="relative cursor-crosshair max-w-full max-h-full shadow-2xl rounded overflow-hidden select-none"
          >
            {deliverable.currentVersion.type === 'video' ? (
              <video
                src={deliverable.currentVersion.previewUrl}
                controls
                className="max-h-[80vh] max-w-full rounded"
              />
            ) : (
              <img
                src={deliverable.currentVersion.previewUrl}
                alt={deliverable.title}
                className="max-h-[80vh] max-w-full object-contain pointer-events-none"
              />
            )}

            {/* Existing Annotation Pins */}
            {pins.map((pin, idx) => (
              <button
                key={pin.id}
                onClick={(e) => {
                  e.stopPropagation();
                  setActivePin(pin);
                }}
                style={{ left: `${pin.x}%`, top: `${pin.y}%` }}
                className={`absolute w-7 h-7 -ml-3.5 -mt-3.5 rounded-full flex items-center justify-center text-xs font-bold shadow-lg transition-transform hover:scale-110 ${
                  pin.isInternal ? 'bg-amber-600 text-white' : 'bg-blue-600 text-white'
                }`}
                title={`Pin by ${pin.author}`}
              >
                {idx + 1}
              </button>
            ))}

            {/* Temporary Placement Marker */}
            {newPinPos && (
              <div
                style={{ left: `${newPinPos.x}%`, top: `${newPinPos.y}%` }}
                className="absolute w-7 h-7 -ml-3.5 -mt-3.5 rounded-full bg-blue-500 border-2 border-white animate-pulse"
              />
            )}
          </div>
        </div>

        {/* Sidebar: Feedback Threads & Active Input */}
        <aside className="w-80 border-l border-slate-800 bg-slate-950 flex flex-col">
          <div className="p-3 border-b border-slate-800 flex justify-between items-center text-xs text-slate-400 uppercase tracking-wider font-semibold">
            <span>Feedback Threads ({pins.length})</span>
            <span className="text-slate-500">Click asset to pin</span>
          </div>

          {/* New Pin Comment Drawer */}
          {newPinPos && (
            <div className="p-4 border-b border-blue-900/50 bg-blue-950/20">
              <span className="text-xs font-semibold text-blue-400 block mb-2">
                New Comment at ({newPinPos.x}%, {newPinPos.y}%)
              </span>
              <textarea
                value={newCommentText}
                onChange={(e) => setNewCommentText(e.target.value)}
                placeholder="Type your feedback here..."
                rows={3}
                className="w-full bg-slate-900 border border-slate-700 rounded p-2 text-sm text-slate-100 focus:outline-none focus:border-blue-500 mb-2"
              />
              {isInternalUser && (
                <label className="flex items-center space-x-2 text-xs text-slate-300 mb-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={isInternalComment}
                    onChange={(e) => setIsInternalComment(e.target.checked)}
                    className="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-0"
                  />
                  <span>Internal Agency Note (Client will not see this)</span>
                </label>
              )}
              <div className="flex space-x-2">
                <button
                  onClick={handleSavePinComment}
                  className="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-xs font-semibold rounded text-white"
                >
                  Post Comment
                </button>
                <button
                  onClick={() => setNewPinPos(null)}
                  className="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-xs rounded text-slate-300"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}

          {/* Comment Threads List */}
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {pins.length === 0 ? (
              <div className="text-center py-12 text-slate-500 text-sm">
                No comments on this version yet.
                <br />
                Click anywhere on the asset to drop a pin.
              </div>
            ) : (
              pins.map((pin, idx) => (
                <div
                  key={pin.id}
                  onClick={() => setActivePin(pin)}
                  className={`p-3 rounded border text-sm transition-colors cursor-pointer ${
                    activePin?.id === pin.id
                      ? 'border-blue-500 bg-slate-900'
                      : 'border-slate-800 bg-slate-900/60 hover:border-slate-700'
                  }`}
                >
                  <div className="flex items-center justify-between mb-1">
                    <span className="font-semibold text-xs text-slate-300 flex items-center space-x-1">
                      <span className="w-4 h-4 rounded-full bg-slate-800 flex items-center justify-center text-[10px] text-slate-400">
                        {idx + 1}
                      </span>
                      <span>{pin.author}</span>
                    </span>
                    <span className="text-[10px] text-slate-500">{pin.createdAt}</span>
                  </div>
                  <p className="text-slate-300 text-xs leading-relaxed">{pin.content}</p>
                  {pin.isInternal && (
                    <span className="inline-block mt-2 text-[10px] font-semibold text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded">
                      Internal Only
                    </span>
                  )}
                </div>
              ))
            )}
          </div>
        </aside>
      </div>

      {/* Explicit Approval Modal */}
      {isApprovalModalOpen && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-slate-950 border border-slate-800 rounded-lg max-w-md w-full p-6 shadow-2xl">
            <h3 className="text-lg font-semibold text-white mb-2">Confirm Deliverable Sign-Off</h3>
            <p className="text-sm text-slate-400 mb-4 leading-relaxed">
              You are about to provide final approval for <strong>v{deliverable.currentVersion.versionNumber}</strong> of{' '}
              <strong>{deliverable.title}</strong>. This action will be permanently recorded in the audit log.
            </p>

            <div className="bg-slate-900 p-3 rounded border border-slate-800 mb-4 font-mono text-[11px] text-slate-400 break-all">
              <span className="block text-slate-500 text-[10px] uppercase font-sans font-semibold mb-0.5">
                Cryptographic SHA-256 Fingerprint:
              </span>
              {deliverable.currentVersion.sha256}
            </div>

            <label className="flex items-start space-x-3 text-xs text-slate-300 mb-6 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={declarationChecked}
                onChange={(e) => setDeclarationChecked(e.target.checked)}
                className="mt-0.5 rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-0"
              />
              <span>
                I confirm that I have reviewed this deliverable version and authorize it for production delivery.
              </span>
            </label>

            <div className="flex space-x-3">
              <button
                onClick={handleConfirmApproval}
                disabled={!declarationChecked || isSubmittingApproval}
                className="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded text-sm font-semibold transition-colors"
              >
                {isSubmittingApproval ? 'Recording...' : 'Confirm Sign-Off'}
              </button>
              <button
                onClick={() => setIsApprovalModalOpen(false)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-sm font-medium"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
