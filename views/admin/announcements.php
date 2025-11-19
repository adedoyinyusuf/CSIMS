<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/message_controller.php';

$messageController = new MessageController();

// Handle POST actions: update, archive, delete
$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_announcement') {
            $id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
            $title = $_POST['announcement_title'] ?? '';
            $content = $_POST['announcement_content'] ?? '';
            $priority = $_POST['announcement_priority'] ?? 'normal';
            $audience = $_POST['target_audience'] ?? 'all';
            $expiry = $_POST['expiry_date'] ?? null;
            $status = $_POST['status'] ?? 'active';
            if ($id <= 0 || empty($title) || empty($content)) {
                $errors[] = 'Invalid input for update.';
            } else {
                $ok = $messageController->updateAnnouncement($id, [
                    'title' => $title,
                    'content' => $content,
                    'priority' => $priority,
                    'target_audience' => $audience,
                    'expiry_date' => $expiry,
                    'status' => $status,
                ]);
                if ($ok) { $success = true; $successMessage = 'Announcement updated successfully'; }
                else { $errors[] = 'Update failed'; }
            }
        } elseif ($action === 'archive_announcement') {
            $id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
            if ($id <= 0) { $errors[] = 'Invalid announcement ID'; }
            else {
                $ok = $messageController->setAnnouncementStatus($id, 'archived');
                if ($ok) { $success = true; $successMessage = 'Announcement archived'; }
                else { $errors[] = 'Archive failed'; }
            }
        } elseif ($action === 'delete_announcement') {
            $id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
            if ($id <= 0) { $errors[] = 'Invalid announcement ID'; }
            else {
                $ok = $messageController->deleteAnnouncement($id);
                if ($ok) { $success = true; $successMessage = 'Announcement deleted'; }
                else { $errors[] = 'Delete failed'; }
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Error processing action: ' . $e->getMessage();
    }
}

// Read filters from GET
$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$audience = $_GET['audience'] ?? 'all';
$expiry_from = $_GET['expiry_from'] ?? '';
$expiry_to = $_GET['expiry_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(50, (int)$_GET['per_page'])) : 10;

$result = $messageController->searchAnnouncementsPaginated([
    'status' => $status,
    'priority' => $priority,
    'target_audience' => $audience,
    'expiry_from' => $expiry_from,
    'expiry_to' => $expiry_to,
    'search' => $search,
    'page' => $page,
    'per_page' => $per_page,
]);

$rows = $result['rows'] ?? [];
$total = $result['total'] ?? 0;
$pages = $result['pages'] ?? 0;
$page = $result['page'] ?? 1;
$per_page = $result['per_page'] ?? $per_page;

function sanitizeHtml($html) {
    // Allow a small set of tags; strip event handlers and javascript: URLs
    $allowed = '<p><br><b><strong><i><em><ul><ol><li><a>';
    $out = strip_tags($html, $allowed);
    // Remove on* attributes and styles
    $out = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $out);
    $out = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $out);
    // Neutralize javascript: in href
    $out = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^\1]*\1/i', 'href="#"', $out);
    return $out;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
                <nav aria-label="breadcrumb" class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Announcements</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bullhorn me-2"></i>Announcements</h1>
                    <a class="btn btn-primary" href="member_communication_portal.php">
                        <i class="fas fa-comments me-1"></i>Communication Portal
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <form class="card p-3 mb-3" method="GET">
                    <div class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?= $status==='all'?'selected':''; ?>>All</option>
                                <option value="active" <?= $status==='active'?'selected':''; ?>>Active</option>
                                <option value="archived" <?= $status==='archived'?'selected':''; ?>>Archived</option>
                                <option value="inactive" <?= $status==='inactive'?'selected':''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="all" <?= $priority==='all'?'selected':''; ?>>All</option>
                                <option value="normal" <?= $priority==='normal'?'selected':''; ?>>Normal</option>
                                <option value="medium" <?= $priority==='medium'?'selected':''; ?>>Medium</option>
                                <option value="high" <?= $priority==='high'?'selected':''; ?>>High</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Audience</label>
                            <select class="form-select" name="audience">
                                <option value="all" <?= $audience==='all'?'selected':''; ?>>All</option>
                                <option value="active" <?= $audience==='active'?'selected':''; ?>>Active Members</option>
                                <option value="expired" <?= $audience==='expired'?'selected':''; ?>>Expired Members</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Expiry From</label>
                            <input type="date" class="form-control" name="expiry_from" value="<?= htmlspecialchars($expiry_from) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Expiry To</label>
                            <input type="date" class="form-control" name="expiry_to" value="<?= htmlspecialchars($expiry_to) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Per Page</label>
                            <select class="form-select" name="per_page">
                                <?php foreach ([10,20,50] as $pp): ?>
                                    <option value="<?= $pp ?>" <?= $per_page===$pp?'selected':''; ?>><?= $pp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title or content">
                        </div>
                        <div class="col-md-6 d-flex align-items-end justify-content-end">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
                            <a class="btn btn-outline-secondary ms-2" href="announcements.php">Reset</a>
                        </div>
                    </div>
                </form>

                <!-- Listing -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Priority</th>
                                        <th>Audience</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Expiry</th>
                                        <th>Preview</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-4">No announcements found</td></tr>
                                    <?php else: foreach ($rows as $a): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($a['title']) ?></td>
                                            <td><span class="badge bg-<?= $a['priority']==='high'?'danger':($a['priority']==='medium'?'warning':'info') ?>"><?= ucfirst($a['priority']) ?></span></td>
                                            <td><?= htmlspecialchars($a['target_audience']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($a['status']) ?></span></td>
                                            <td><?= date('M d, Y H:i', strtotime($a['created_at'])) ?></td>
                                            <td><?= !empty($a['expiry_date']) ? date('M d, Y H:i', strtotime($a['expiry_date'])) : '-' ?></td>
                                            <td style="max-width:300px;">
                                                <div class="small"><?= sanitizeHtml($a['content']) ?></div>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal"
                                                        data-announcement-id="<?= (int)$a['announcement_id'] ?>"
                                                        data-title="<?= htmlspecialchars($a['title']) ?>"
                                                        data-content='<?= htmlspecialchars($a['content'], ENT_QUOTES) ?>'
                                                        data-priority="<?= htmlspecialchars($a['priority']) ?>"
                                                        data-audience="<?= htmlspecialchars($a['target_audience']) ?>"
                                                        data-expiry="<?= htmlspecialchars($a['expiry_date'] ?? '') ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="archive_announcement">
                                                        <input type="hidden" name="announcement_id" value="<?= (int)$a['announcement_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-warning"><i class="fas fa-archive"></i></button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                                        <input type="hidden" name="action" value="delete_announcement">
                                                        <input type="hidden" name="announcement_id" value="<?= (int)$a['announcement_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <div>
                            Showing page <?= $page ?> of <?= max(1, $pages) ?> — <?= $total ?> total
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php $base = 'announcements.php?'.http_build_query(array_merge($_GET, ['page'=>null])); ?>
                                <li class="page-item <?= $page<=1?'disabled':''; ?>">
                                    <a class="page-link" href="<?= $page<=1 ? '#' : $base . '&page=' . ($page-1) ?>">Previous</a>
                                </li>
                                <?php for ($p=max(1,$page-2); $p<=min($pages,$page+2); $p++): ?>
                                    <li class="page-item <?= $p===$page?'active':''; ?>">
                                        <a class="page-link" href="<?= $base . '&page=' . $p ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page>=$pages?'disabled':''; ?>">
                                    <a class="page-link" href="<?= $page>=$pages ? '#' : $base . '&page=' . ($page+1) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Edit Announcement Modal -->
                <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Announcement</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_announcement">
                                <input type="hidden" id="edit_announcement_id" name="announcement_id" value="">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="form-label">Title</label>
                                            <input type="text" class="form-control" id="edit_announcement_title" name="announcement_title" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Priority</label>
                                            <select class="form-select" id="edit_announcement_priority" name="announcement_priority">
                                                <option value="normal">Normal</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Target Audience</label>
                                            <select class="form-select" id="edit_target_audience" name="target_audience">
                                                <option value="all">All Members</option>
                                                <option value="active">Active Members</option>
                                                <option value="expired">Expired Members</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Expiry Date (Optional)</label>
                                            <input type="datetime-local" class="form-control" id="edit_expiry_date" name="expiry_date">
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label">Content</label>
                                        <textarea class="form-control" id="edit_announcement_content" name="announcement_content" rows="8" required></textarea>
                                    </div>
                                    <input type="hidden" id="edit_status" name="status" value="active">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#edit_announcement_content').summernote({
                height: 300,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });

            const editModalEl = document.getElementById('editAnnouncementModal');
            if (editModalEl) {
                editModalEl.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (!button) return;
                    const id = button.getAttribute('data-announcement-id');
                    const title = button.getAttribute('data-title') || '';
                    const content = button.getAttribute('data-content') || '';
                    const priority = button.getAttribute('data-priority') || 'normal';
                    const audience = button.getAttribute('data-audience') || 'all';
                    const expiry = button.getAttribute('data-expiry') || '';

                    document.getElementById('edit_announcement_id').value = id;
                    document.getElementById('edit_announcement_title').value = title;
                    document.getElementById('edit_announcement_priority').value = priority;
                    document.getElementById('edit_target_audience').value = audience;
                    document.getElementById('edit_expiry_date').value = expiry;
                    $('#edit_announcement_content').summernote('code', content);
                });
            }
        });
    </script>
</body>
</html>