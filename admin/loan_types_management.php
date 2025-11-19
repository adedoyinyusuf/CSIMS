<?php
session_start();

require_once '../classes/LoanTypeService.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$loanTypeService = new LoanTypeService();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'type_name' => $_POST['type_name'],
                    'description' => $_POST['description'],
                    'interest_rate' => (float)$_POST['interest_rate'],
                    'min_amount' => $_POST['min_amount'] ? (float)$_POST['min_amount'] : null,
                    'max_amount' => $_POST['max_amount'] ? (float)$_POST['max_amount'] : null,
                    'min_term_months' => $_POST['min_term_months'] ? (int)$_POST['min_term_months'] : null,
                    'max_term_months' => $_POST['max_term_months'] ? (int)$_POST['max_term_months'] : null,
                    'processing_fee_rate' => (float)$_POST['processing_fee_rate'],
                    'requires_guarantor' => isset($_POST['requires_guarantor']) ? 1 : 0,
                    'guarantor_count' => (int)$_POST['guarantor_count'],
                    'collateral_required' => isset($_POST['collateral_required']) ? 1 : 0,
                    'grace_period_days' => (int)$_POST['grace_period_days'],
                    'penalty_rate' => (float)$_POST['penalty_rate'],
                    'auto_disburse' => isset($_POST['auto_disburse']) ? 1 : 0,
                    'disburse_delay_hours' => (int)$_POST['disburse_delay_hours'],
                    'display_order' => (int)$_POST['display_order'],
                    'eligibility_criteria' => !empty($_POST['eligibility_criteria']) ? $_POST['eligibility_criteria'] : null
                ];
                
                $loanTypeId = $loanTypeService->createLoanType($data);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Loan type created successfully',
                    'loan_type_id' => $loanTypeId
                ]);
                break;
                
            case 'update':
                $loanTypeId = (int)$_POST['loan_type_id'];
                $data = [
                    'type_name' => $_POST['type_name'],
                    'description' => $_POST['description'],
                    'interest_rate' => (float)$_POST['interest_rate'],
                    'min_amount' => $_POST['min_amount'] ? (float)$_POST['min_amount'] : null,
                    'max_amount' => $_POST['max_amount'] ? (float)$_POST['max_amount'] : null,
                    'min_term_months' => $_POST['min_term_months'] ? (int)$_POST['min_term_months'] : null,
                    'max_term_months' => $_POST['max_term_months'] ? (int)$_POST['max_term_months'] : null,
                    'processing_fee_rate' => (float)$_POST['processing_fee_rate'],
                    'requires_guarantor' => isset($_POST['requires_guarantor']) ? 1 : 0,
                    'guarantor_count' => (int)$_POST['guarantor_count'],
                    'collateral_required' => isset($_POST['collateral_required']) ? 1 : 0,
                    'grace_period_days' => (int)$_POST['grace_period_days'],
                    'penalty_rate' => (float)$_POST['penalty_rate'],
                    'auto_disburse' => isset($_POST['auto_disburse']) ? 1 : 0,
                    'disburse_delay_hours' => (int)$_POST['disburse_delay_hours'],
                    'display_order' => (int)$_POST['display_order'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'eligibility_criteria' => !empty($_POST['eligibility_criteria']) ? $_POST['eligibility_criteria'] : null
                ];
                
                $loanTypeService->updateLoanType($loanTypeId, $data);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Loan type updated successfully'
                ]);
                break;
                
            case 'delete':
                $loanTypeId = (int)$_POST['loan_type_id'];
                $hardDelete = isset($_POST['hard_delete']) && $_POST['hard_delete'] === '1';
                
                $loanTypeService->deleteLoanType($loanTypeId, $hardDelete);
                
                echo json_encode([
                    'success' => true,
                    'message' => $hardDelete ? 'Loan type deleted permanently' : 'Loan type deactivated'
                ]);
                break;
                
            case 'get_loan_type':
                $loanTypeId = (int)$_POST['loan_type_id'];
                $loanType = $loanTypeService->getLoanTypeById($loanTypeId);
                
                echo json_encode([
                    'success' => true,
                    'loan_type' => $loanType
                ]);
                break;
                
            case 'calculate_preview':
                $loanTypeId = (int)$_POST['loan_type_id'];
                $amount = (float)$_POST['amount'];
                $termMonths = (int)$_POST['term_months'];
                
                $preview = $loanTypeService->calculateLoanPreview($loanTypeId, $amount, $termMonths);
                
                echo json_encode([
                    'success' => true,
                    'preview' => $preview
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// Get all loan types
$loanTypes = $loanTypeService->getAllLoanTypes(true); // Include inactive ones for admin view
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Types Management - CSIMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .loan-type-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007cba;
        }
        
        .loan-type-card.inactive {
            opacity: 0.7;
            border-left-color: #6c757d;
        }
        
        .utilization-bar {
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .utilization-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745 0%, #ffc107 70%, #dc3545 100%);
            transition: width 0.3s ease;
        }
        
        .badge-rate {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        .modal-lg {
            max-width: 900px;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .preview-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .criteria-editor {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h2><i class="fas fa-cogs me-2"></i>Loan Types Management</h2>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-success me-3" data-bs-toggle="modal" data-bs-target="#loanTypeModal" onclick="openCreateModal()">
                            <i class="fas fa-plus me-1"></i>New Loan Type
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-primary">
                            <i class="fas fa-list-alt fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= count(array_filter($loanTypes, function($lt) { return $lt['is_active']; })) ?></h3>
                        <p class="text-muted mb-0">Active Loan Types</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-success">
                            <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1"><?= array_sum(array_column($loanTypes, 'total_loans')) ?></h3>
                        <p class="text-muted mb-0">Total Loans</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-warning">
                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                        </div>
                        <h3 class="mb-1">₦<?= number_format(array_sum(array_column($loanTypes, 'outstanding_amount')), 0) ?></h3>
                        <p class="text-muted mb-0">Outstanding Amount</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="text-info">
                            <i class="fas fa-percentage fa-3x mb-3"></i>
                        </div>
                        <?php 
                        $avgRate = count($loanTypes) > 0 ? array_sum(array_column($loanTypes, 'interest_rate')) / count($loanTypes) : 0;
                        ?>
                        <h3 class="mb-1"><?= number_format($avgRate, 1) ?>%</h3>
                        <p class="text-muted mb-0">Average Interest Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loan Types Cards -->
        <div class="row mt-4">
            <?php foreach ($loanTypes as $loanType): ?>
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card loan-type-card <?= $loanType['is_active'] ? '' : 'inactive' ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($loanType['type_name']) ?></h6>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="editLoanType(<?= $loanType['id'] ?>)">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="viewLoanType(<?= $loanType['id'] ?>)">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($loanType['is_active']): ?>
                                <li><a class="dropdown-item text-warning" href="#" onclick="deleteLoanType(<?= $loanType['id'] ?>, false)">
                                    <i class="fas fa-pause me-1"></i>Deactivate
                                </a></li>
                                <?php else: ?>
                                <li><a class="dropdown-item text-success" href="#" onclick="activateLoanType(<?= $loanType['id'] ?>)">
                                    <i class="fas fa-play me-1"></i>Activate
                                </a></li>
                                <?php endif; ?>
                                <?php if ($loanType['active_loans'] == 0): ?>
                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteLoanType(<?= $loanType['id'] ?>, true)">
                                    <i class="fas fa-trash me-1"></i>Delete Permanently
                                </a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Status and Rate -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-<?= $loanType['is_active'] ? 'success' : 'secondary' ?> badge-rate">
                                <?= $loanType['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <span class="h5 mb-0 text-primary"><?= $loanType['interest_rate'] ?>% APR</span>
                        </div>
                        
                        <!-- Description -->
                        <p class="text-muted small mb-3"><?= htmlspecialchars($loanType['description']) ?></p>
                        
                        <!-- Loan Limits -->
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">Min Amount</small>
                                <strong>₦<?= $loanType['min_amount'] ? number_format($loanType['min_amount'], 0) : 'No limit' ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Max Amount</small>
                                <strong>₦<?= $loanType['max_amount'] ? number_format($loanType['max_amount'], 0) : 'No limit' ?></strong>
                            </div>
                        </div>
                        
                        <!-- Term Limits -->
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">Min Term</small>
                                <strong><?= $loanType['min_term_months'] ?? 'No limit' ?> months</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Max Term</small>
                                <strong><?= $loanType['max_term_months'] ?? 'No limit' ?> months</strong>
                            </div>
                        </div>
                        
                        <!-- Utilization Bar -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Utilization</small>
                                <small class="fw-bold"><?= number_format($loanType['utilization_rate'], 1) ?>%</small>
                            </div>
                            <div class="utilization-bar">
                                <div class="utilization-fill" style="width: <?= min(100, $loanType['utilization_rate']) ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fw-bold text-primary"><?= $loanType['total_loans'] ?></div>
                                <small class="text-muted">Total Loans</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-success"><?= $loanType['active_loans'] ?></div>
                                <small class="text-muted">Active</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-info"><?= number_format($loanType['approval_rate'], 0) ?>%</div>
                                <small class="text-muted">Approval</small>
                            </div>
                        </div>
                        
                        <!-- Quick Features -->
                        <div class="mt-3 d-flex flex-wrap gap-1">
                            <?php if ($loanType['requires_guarantor']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-users me-1"></i><?= $loanType['guarantor_count'] ?> Guarantor<?= $loanType['guarantor_count'] > 1 ? 's' : '' ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($loanType['collateral_required']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-shield-alt me-1"></i>Collateral
                                </span>
                            <?php endif; ?>
                            <?php if ($loanType['auto_disburse']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-bolt me-1"></i>Auto Disburse
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Empty State -->
        <?php if (empty($loanTypes)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-list-alt fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Loan Types Found</h4>
                    <p class="text-muted mb-4">Create your first loan type to get started.</p>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loanTypeModal" onclick="openCreateModal()">
                        <i class="fas fa-plus me-1"></i>Create Loan Type
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loan Type Modal -->
    <div class="modal fade" id="loanTypeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loanTypeModalTitle">Create New Loan Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="loanTypeForm">
                        <input type="hidden" id="loanTypeId" name="loan_type_id">
                        <input type="hidden" id="formAction" name="action" value="create">
                        
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="typeName" name="type_name" required>
                                    <label for="typeName">Loan Type Name *</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="displayOrder" name="display_order" value="999">
                                    <label for="displayOrder">Display Order</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-floating">
                            <textarea class="form-control" id="description" name="description" style="height: 80px;" required></textarea>
                            <label for="description">Description *</label>
                        </div>
                        
                        <!-- Interest and Fees -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="interestRate" name="interest_rate" step="0.01" min="0" required>
                                    <label for="interestRate">Interest Rate (%) *</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="processingFeeRate" name="processing_fee_rate" step="0.01" min="0" value="1.0">
                                    <label for="processingFeeRate">Processing Fee (%)</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="penaltyRate" name="penalty_rate" step="0.01" min="0" value="5.0">
                                    <label for="penaltyRate">Penalty Rate (%)</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Amount Limits -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="minAmount" name="min_amount" step="0.01" min="0">
                                    <label for="minAmount">Minimum Amount (₦)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="maxAmount" name="max_amount" step="0.01" min="0">
                                    <label for="maxAmount">Maximum Amount (₦)</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Term Limits -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="minTermMonths" name="min_term_months" min="1">
                                    <label for="minTermMonths">Minimum Term (Months)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="maxTermMonths" name="max_term_months" min="1">
                                    <label for="maxTermMonths">Maximum Term (Months)</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Requirements -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="requiresGuarantor" name="requires_guarantor">
                                    <label class="form-check-label" for="requiresGuarantor">
                                        Requires Guarantor
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="guarantorCount" name="guarantor_count" min="0" max="5" value="1">
                                    <label for="guarantorCount">Number of Guarantors</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="collateralRequired" name="collateral_required">
                                    <label class="form-check-label" for="collateralRequired">
                                        Collateral Required
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Grace Period and Auto Disbursement -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="gracePeriodDays" name="grace_period_days" min="0" value="30">
                                    <label for="gracePeriodDays">Grace Period (Days)</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="autoDisburse" name="auto_disburse">
                                    <label class="form-check-label" for="autoDisburse">
                                        Auto Disbursement
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="disburseDelayHours" name="disburse_delay_hours" min="0" value="24">
                                    <label for="disburseDelayHours">Disburse Delay (Hours)</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status (only for edit) -->
                        <div class="row" id="statusRow" style="display: none;">
                            <div class="col-md-12">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                                    <label class="form-check-label" for="isActive">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Eligibility Criteria -->
                        <div class="mb-3">
                            <label for="eligibilityCriteria" class="form-label">Eligibility Criteria (JSON)</label>
                            <textarea class="form-control criteria-editor" id="eligibilityCriteria" name="eligibility_criteria" rows="4" placeholder='{"min_membership_months": 6, "min_savings_amount": 10000, "max_active_loans": 2, "savings_multiplier": 3}'></textarea>
                            <div class="form-text">
                                Optional JSON configuration for member eligibility requirements.
                            </div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="preview-section" id="previewSection" style="display: none;">
                            <h6>Loan Preview</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="previewAmount" step="1000" min="0" placeholder="Enter amount">
                                        <label for="previewAmount">Preview Amount (₦)</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="previewTerm" min="1" value="12">
                                        <label for="previewTerm">Term (Months)</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary w-100" style="height: 58px;" onclick="calculatePreview()">
                                        Calculate Preview
                                    </button>
                                </div>
                            </div>
                            <div id="previewResults" class="mt-3"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveLoanType()">
                        <span class="spinner-border spinner-border-sm me-1" id="saveSpinner" style="display: none;"></span>
                        Save Loan Type
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let isEditing = false;

        function openCreateModal() {
            isEditing = false;
            document.getElementById('loanTypeModalTitle').textContent = 'Create New Loan Type';
            document.getElementById('formAction').value = 'create';
            document.getElementById('loanTypeForm').reset();
            document.getElementById('statusRow').style.display = 'none';
            document.getElementById('previewSection').style.display = 'none';
        }

        function editLoanType(loanTypeId) {
            isEditing = true;
            document.getElementById('loanTypeModalTitle').textContent = 'Edit Loan Type';
            document.getElementById('formAction').value = 'update';
            document.getElementById('loanTypeId').value = loanTypeId;
            document.getElementById('statusRow').style.display = 'block';
            document.getElementById('previewSection').style.display = 'block';

            // Fetch loan type data
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_loan_type&loan_type_id=${loanTypeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const lt = data.loan_type;
                    
                    // Populate form fields
                    document.getElementById('typeName').value = lt.type_name || '';
                    document.getElementById('description').value = lt.description || '';
                    document.getElementById('interestRate').value = lt.interest_rate || '';
                    document.getElementById('minAmount').value = lt.min_amount || '';
                    document.getElementById('maxAmount').value = lt.max_amount || '';
                    document.getElementById('minTermMonths').value = lt.min_term_months || '';
                    document.getElementById('maxTermMonths').value = lt.max_term_months || '';
                    document.getElementById('processingFeeRate').value = lt.processing_fee_rate || '1.0';
                    document.getElementById('requiresGuarantor').checked = lt.requires_guarantor == 1;
                    document.getElementById('guarantorCount').value = lt.guarantor_count || '1';
                    document.getElementById('collateralRequired').checked = lt.collateral_required == 1;
                    document.getElementById('gracePeriodDays').value = lt.grace_period_days || '30';
                    document.getElementById('penaltyRate').value = lt.penalty_rate || '5.0';
                    document.getElementById('autoDisburse').checked = lt.auto_disburse == 1;
                    document.getElementById('disburseDelayHours').value = lt.disburse_delay_hours || '24';
                    document.getElementById('displayOrder').value = lt.display_order || '999';
                    document.getElementById('isActive').checked = lt.is_active == 1;
                    document.getElementById('eligibilityCriteria').value = lt.eligibility_criteria || '';
                    
                    // Show modal
                    new bootstrap.Modal(document.getElementById('loanTypeModal')).show();
                } else {
                    showAlert('danger', 'Error loading loan type: ' + data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Error loading loan type: ' + error.message);
            });
        }

        function viewLoanType(loanTypeId) {
            // For now, just edit - can implement a separate view modal later
            editLoanType(loanTypeId);
        }

        function deleteLoanType(loanTypeId, hardDelete) {
            const action = hardDelete ? 'permanently delete' : 'deactivate';
            const message = `Are you sure you want to ${action} this loan type?`;
            
            if (confirm(message)) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&loan_type_id=${loanTypeId}&hard_delete=${hardDelete ? '1' : '0'}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Error: ' + error.message);
                });
            }
        }

        function activateLoanType(loanTypeId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update&loan_type_id=${loanTypeId}&is_active=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Loan type activated successfully');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Error: ' + error.message);
            });
        }

        function saveLoanType() {
            const form = document.getElementById('loanTypeForm');
            const formData = new FormData(form);
            const saveBtn = document.querySelector('.modal-footer .btn-primary');
            const spinner = document.getElementById('saveSpinner');
            
            // Show loading state
            saveBtn.disabled = true;
            spinner.style.display = 'inline-block';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('loanTypeModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Error: ' + error.message);
            })
            .finally(() => {
                saveBtn.disabled = false;
                spinner.style.display = 'none';
            });
        }

        function calculatePreview() {
            if (!isEditing) return;
            
            const loanTypeId = document.getElementById('loanTypeId').value;
            const amount = document.getElementById('previewAmount').value;
            const term = document.getElementById('previewTerm').value;
            
            if (!amount || !term) {
                showAlert('warning', 'Please enter amount and term for preview');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=calculate_preview&loan_type_id=${loanTypeId}&amount=${amount}&term_months=${term}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const preview = data.preview;
                    document.getElementById('previewResults').innerHTML = `
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted d-block">Monthly Payment</small>
                                <strong class="text-primary">₦${preview.monthly_payment.toLocaleString()}</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Total Interest</small>
                                <strong class="text-info">₦${preview.total_interest.toLocaleString()}</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Processing Fee</small>
                                <strong class="text-warning">₦${preview.processing_fee.toLocaleString()}</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Total Amount</small>
                                <strong class="text-success">₦${preview.total_amount.toLocaleString()}</strong>
                            </div>
                        </div>
                    `;
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Preview calculation error: ' + error.message);
            });
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Enable/disable guarantor count based on checkbox
        document.getElementById('requiresGuarantor').addEventListener('change', function() {
            document.getElementById('guarantorCount').disabled = !this.checked;
        });

        // Enable/disable disburse delay based on auto disburse checkbox
        document.getElementById('autoDisburse').addEventListener('change', function() {
            document.getElementById('disburseDelayHours').disabled = !this.checked;
        });
    </script>
</body>
</html>