export interface AuditBreakdown {
    formatting: number;
    content_quality: number;
    industry_relevance: number;
}

export interface TechStackItem {
    name: string;
    version?: string;
    badge_color?: string;
    category?: string;
}

export interface TechAssessment {
    architecture: string;
    quality_score: number;
    stack: TechStackItem[];
}

export interface SecurityAssessment {
    risk_level: 'low' | 'medium' | 'high' | 'critical';
    score: number;
    vulnerabilities: string[];
}

export interface HandshakeProof {
    status: 'Verified' | 'Failed' | 'Skipped';
    method: string;
    details: string;
}

export interface AuditMetadata {
    is_professional: boolean;
    document_type: 'Resume' | 'Contract' | 'Invoice' | 'Certificate' | 'Visual PDF' | 'Error' | 'Processing' | 'Other';
    category?: 'Template' | 'Dataset' | 'Public Audit' | 'Educational' | 'Personal Document' | 'Contract' | 'Certificate' | 'Creative Work' | 'Other';
    confidence_score: number;
    score?: number;
    breakdown?: AuditBreakdown | ProjectBreakdown;
    recommendations?: string[];
    summary: string;
    business_overview?: string;
    flag_reason?: string;
    ocr_required?: boolean;
    is_marketplace_eligible?: boolean;
    privacy_warning?: string | null;
    pii_reason?: string | null;
    audit_type?: 'document' | 'project' | 'design';
    credit_cost?: number;
    visual_proofs?: any[]; // For Design Audits

    // Project-specific fields
    analysis_type?: string;
    tech_assessment?: TechAssessment;
    security_assessment?: SecurityAssessment;
    handshake_proof?: HandshakeProof | string; // Support legacy string format too
    transferability_assessment?: any;
    scalability_score?: number;
    insights?: string[];
    warning_flags?: string[];
    suggested_value_multiplier?: number;
    estimated_remediation_cost?: number; // New field for Technical Debt
    eligibility_reason?: string;
    website_url?: string;
    github_repo_url?: string;

    // Deep Context Fields
    niche?: string;
    site_classification?: string;
    business_summary?: string;
    vector_details?: {
        [key: string]: {
            explanation: string;
            status_label: 'Good' | 'Risk' | 'Critical';
            improvement_tip: string;
        }
    };
    tech_footprint_explanations?: { [key: string]: string };
    risk_matrix?: {
        performance: 'low' | 'med' | 'high';
        security: 'low' | 'med' | 'high';
        scalability: 'low' | 'med' | 'high';
    };

    // Nested metadata from Project Audits
    metadata?: {
        niche?: string;
        project_type?: string;
    };

    // Legacy / optional nested report
    detailed_forensic_report?: {
        summary: string;
        tech_stack: { name: string; version: string; confidence: string; badge_color: string }[];
        security_audit: { issue: string; severity: string; fix: string }[];
        handshake_proof: string;
        deep_insights: string[];
        ux_violations?: string[];
        mobile_analysis?: string;
        component_library_detected?: boolean;
        scalability_metrics?: {
            performance_score: number;
            security_score: number;
            maintainability_score: number;
        };
        estimated_remediation_cost?: number;
    };
    tech_stack?: any[]; // Legacy fallback
    project_type?: string;
}

export interface ProjectBreakdown {
    tech_score: number;
    security_score: number;
    scalability_score: number;
}

export interface VaultAsset {
    id: string; // UUID
    user_id?: number | string; // Owner's user ID for private channels
    file_name: string;
    file_path: string;
    file_size: number;
    mime_type: string;
    status: 'pending' | 'uploaded' | 'processing' | 'ready' | 'verified' | 'verified_private' | 'flagged' | 'payment_required' | 'action_required';
    metadata?: AuditMetadata | null;
    suggested_value?: number | null; // AI estimated market value
    is_for_sale?: boolean;
    price?: number | null;
    sale_count?: number;
    score?: number;      // Added for UI access
    radar_data?: any;    // Added for UI access (Project/Design audits)
    full_audit_report?: string; // Full AI-generated report in Markdown
    github_repo_url?: string;   // Linked GitHub repository URL
    created_at: string;
    updated_at: string;
}

export interface PresignedUrlResponse {
    upload_url: string;           // The presigned URL for PUT request
    asset_id: string;             // UUID of the created vault asset
    headers: {                    // Required headers for the upload
        'Content-Type': string;
    };
    expires_at: string;           // ISO 8601 timestamp when URL expires
}

export interface ConfirmUploadResponse {
    success: boolean;
    asset_id: string;
    status: 'processing' | 'verified' | 'verified_private' | 'flagged' | 'payment_required' | 'action_required';
    payout: number;
    message?: string;
    metadata?: AuditMetadata;
    file_size?: number;
}

export interface DownloadUrlResponse {
    download_url: string;
    file_name: string;
    expires_at: string;
}

export interface DeleteAssetResponse {
    success: boolean;
    asset_id: string;
}