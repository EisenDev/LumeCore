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
    custom_name?: string;
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
    audit_type?: 'document' | 'project' | 'design' | 'repository_scan' | 'sync_report';
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
    // Deep Context Fields
    niche?: string;
    site_classification?: string;
    business_summary?: string;

    // Security & QA Audit (New)
    security_audit?: {
        security_score: number;
        qa_score: number;
        vulnerabilities: Array<{
            severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
            type: string;
            location: string;
            description: string;
            remediation: string;
        }>;
        qa_issues: Array<{
            priority: string;
            issue: string;
            location: string;
            fix: string;
        }>;
        attack_vectors_identified: string[];
        compliance_check: {
            ssl_status: string;
            cookie_security: string;
        };
        executive_summary: string;
    };

    vector_details?: {
        [key: string]: {
            explanation: string;
            status_label: 'Good' | 'Risk' | 'Critical';
            improvement_tip: string;
        }
    };
    website_url?: string;
    github_repo_url?: string;
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
        // Sync Report Fields
        synced_with_repo_id?: string;
        latest_sync_comparison?: any;
        comparison_status_label?: string;
        hexagon_vectors?: any;
        comparison_data?: any;
        web_asset_id?: string;
        repo_asset_id?: string;
    };
    // Root level fallbacks for specific sync structure
    synced_with_repo_id?: string;
    latest_sync_comparison?: any;
    comparison_status_label?: string;
    hexagon_vectors?: any;
    comparison_data?: any;
    web_asset_id?: string;
    repo_asset_id?: string;

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
    id: string;
    user_id: number;
    file_name: string;
    file_path: string;
    file_size: number;
    mime_type: string;
    status: 'pending' | 'uploaded' | 'processing' | 'verified' | 'flagged' | 'action_required' | 'ready' | 'verified_private';
    score?: number;
    metadata: {
        summary?: string;
        is_professional?: boolean;
        document_type?: string;
        audit_type?: string;
        website_url?: string;
        github_repo_url?: string;
        is_sync_scan?: boolean; // For Sync Detection
        config?: {
            mode?: string;
            [key: string]: any;
        };
        [key: string]: any;
    } | null;
    created_at: string;
    updated_at: string;
    // Relationships
    user?: User;
    purchase?: any;
    latest_sync_score?: number;
    suggested_value?: number | null; // AI estimated market value
    is_for_sale?: boolean;
    price?: number | null;
    sale_count?: number;
    radar_data?: any;    // Added for UI access (Project/Design audits)
    full_audit_report?: string; // Full AI-generated report in Markdown
    original_url?: string;      // Original URL for web assets

    // Real Marketplace Data (Sync Scan Results)
    synced_assets?: { web: string; repo: string };
    website_url?: string | null;
    repository_url?: string | null;
    website_metadata?: AuditMetadata | any;
    repository_metadata?: AuditMetadata | any;
    document_metadata?: any;
    synced_metadata?: { hexagon_vectors: any } | any;
    sync_score?: number | string; // Decimal often comes as string from JSON
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
    asset?: VaultAsset;
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