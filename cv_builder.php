<?php
session_start();
require_once 'config/db.php';

// Auth Check
if (!isset($_SESSION['user_id']) OR $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch Student Profile
$stmt_std = $conn->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = ?");
$stmt_std->bind_param("i", $user_id);
$stmt_std->execute();
$student = $stmt_std->get_result()->fetch_assoc();
$student_id = $student['student_id'] ?? 0;
$profile_pic = $student['profilePicture'] ?? $student['profile_picture'] ?? '';

// Parse existing JSON CV data or load default template
$cv_data = !empty($student['cv_data']) ? json_decode($student['cv_data'], true) : [
    'summary' => '',
    'education' => [['institution' => 'University of Mpumalanga', 'qualification' => $student['qualification'] ?? 'Diploma in ICT', 'year' => '2023 - Present']],
    'skills' => $student['skills'] ?? 'PHP, MySQL, JavaScript, Java, HTML/CSS',
    'projects' => [['title' => 'NextStep ICT Portal', 'tech' => 'PHP, MySQL, Bootstrap', 'description' => 'Developed role-based career management platform.']],
    'experience' => [],
    'references' => [['name' => 'Academic Coordinator', 'title' => 'Senior Lecturer - UMP', 'contact' => 'ict@ump.ac.za']]
];

// Handle Form Submission / Save CV
if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['save_cv'])) {
    $updated_cv = [
        'summary' => trim($_POST['summary'] ?? ''),
        'education' => $_POST['education'] ?? [],
        'skills' => trim($_POST['skills'] ?? ''),
        'projects' => $_POST['projects'] ?? [],
        'experience' => $_POST['experience'] ?? [],
        'references' => $_POST['references'] ?? []
    ];

    $template = trim($_POST['cv_template'] ?? 'modern');
    $json_cv = json_encode($updated_cv);

    // Profile Picture / Headshot Upload Handling
    if (isset($_FILES['cv_picture']) AND $_FILES['cv_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['cv_picture']['tmp_name'];
        $fileName = $_FILES['cv_picture']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'profile_' . $student_id . '_' . time() . '.' . $fileExtension;
            $uploadFileDir = 'uploads/profile_pictures/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $stmt_pic = $conn->prepare("UPDATE students SET profilePicture = ? WHERE student_id = ?");
                if (!$stmt_pic) {
                    $stmt_pic = $conn->prepare("UPDATE students SET profile_picture = ? WHERE student_id = ?");
                }
                if ($stmt_pic AND $stmt_pic->bind_param("si", $dest_path, $student_id) AND $stmt_pic->execute()) {
                    $profile_pic = $dest_path;
                }
            } else {
                $error = 'Error uploading picture file.';
            }
        } else {
            $error = 'Invalid picture format. Use JPG, PNG, or WEBP.';
        }
    }

    if (empty($error)) {
        // Proof of registration parameter removed completely as requested
        $stmt_save = $conn->prepare("UPDATE students SET cv_data = ?, cv_template = ? WHERE student_id = ?");
        $stmt_save->bind_param("ssi", $json_cv, $template, $student_id);
        if ($stmt_save->execute()) {
            $success = 'CV updated and saved successfully!';
            $cv_data = $updated_cv;
            $student['cv_template'] = $template;
        } else {
            $error = 'Failed to save CV data: ' . $conn->error;
        }
    }
}

// Profile Completion Calculation
$completed_sections = 0;
$total_sections = 7;
if (!empty($_SESSION['full_name'])) $completed_sections++;
if (!empty($profile_pic)) $completed_sections++;
if (!empty($cv_data['summary'])) $completed_sections++;
if (!empty($cv_data['education'])) $completed_sections++;
if (!empty($cv_data['skills'])) $completed_sections++;
if (!empty($cv_data['projects'])) $completed_sections++;
if (!empty($cv_data['references'])) $completed_sections++;
$completion_pct = round(($completed_sections / $total_sections) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive CV Builder | NextStep ICT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --ump-navy: #132238;
            --ump-gold: #f59e0b;
            --ump-light: #f8fafc;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--ump-light); margin: 0; padding: 0; color: #334155; }
        .nav-bar { background: #fff; padding: 0.85rem 2rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { font-weight: 800; color: var(--ump-navy); text-decoration: none; font-size: 1.2rem; }
        .builder-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; max-width: 1400px; margin: 1.5rem auto; padding: 0 1.5rem; }
        .editor-pane, .preview-pane { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; height: calc(100vh - 120px); overflow-y: auto; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem; }
        .form-control { width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .btn { background: var(--ump-navy); color: #fff; padding: 0.65rem 1.2rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-gold { background: var(--ump-gold); color: #fff; }
        .btn-gold:hover { background: #d97706; }
        .section-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        
        #cv-paper { background: #fff; padding: 2.5rem; border: 1px solid #cbd5e1; min-height: 800px; font-size: 0.9rem; }
        .tmpl-modern { border-top: 6px solid var(--ump-navy); }
        .tmpl-classic { font-family: Georgia, serif; }
        .cv-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1rem; }
        .cv-header h2 { margin: 0; color: var(--ump-navy); font-size: 1.6rem; }
        .cv-sec-title { font-size: 1.05rem; font-weight: 700; color: var(--ump-navy); border-bottom: 1px solid #cbd5e1; padding-bottom: 0.2rem; margin: 1rem 0 0.5rem 0; text-transform: uppercase; }
        .progress-bar { background: #e2e8f0; height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 1rem; }
        .progress-fill { background: var(--ump-gold); height: 100%; }
    </style>
</head>
<body>

    <nav class="nav-bar">
        <a href="student_dashboard.php" class="nav-brand"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        <div style="font-weight: 700; color: var(--ump-navy);"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--ump-gold);"></i> Comprehensive CV Builder</div>
        <button onclick="downloadPDF()" class="btn btn-gold"><i class="fa-solid fa-file-pdf"></i> Export PDF CV</button>
    </nav>

    <div class="builder-container">
        <!-- Editor Pane -->
        <div class="editor-pane">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--ump-navy);">CV Information Editor</h3>
                <span style="font-size: 0.85rem; font-weight: 700;">Completion: <?= $completion_pct ?>%</span>
            </div>
            <div class="progress-bar" style="margin-top: 0.5rem;">
                <div class="progress-fill" style="width: <?= $completion_pct ?>%;"></div>
            </div>

            <?php if (!empty($success)): ?>
                <div style="background:#f0fdf4; color:#166534; padding:0.75rem; border-radius:6px; margin-bottom:1rem; font-size:0.85rem;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background:#fef2f2; color:#991b1b; padding:0.75rem; border-radius:6px; margin-bottom:1rem; font-size:0.85rem;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="cv_builder.php" enctype="multipart/form-data">
                <input type="hidden" name="save_cv" value="1">

                <div class="form-group">
                    <label>Select CV Style Template</label>
                    <select name="cv_template" id="template-select" class="form-control" onchange="updatePreview()">
                        <option value="modern" <?= ($student['cv_template'] ?? '') === 'modern' ? 'selected' : '' ?>>UMP Modern Navy</option>
                        <option value="classic" <?= ($student['cv_template'] ?? '') === 'classic' ? 'selected' : '' ?>>Executive Classic</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-image" style="color: var(--ump-gold);"></i> Upload CV Headshot Picture</label>
                    <input type="file" name="cv_picture" class="form-control" accept="image/jpeg, image/png, image/webp" onchange="previewImage(this)">
                </div>

                <div class="form-group">
                    <label>Professional Summary</label>
                    <textarea name="summary" id="input-summary" class="form-control" rows="3" oninput="updatePreview()"><?= htmlspecialchars($cv_data['summary'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Technical & Soft Skills (Comma Separated)</label>
                    <input type="text" name="skills" id="input-skills" class="form-control" value="<?= htmlspecialchars($cv_data['skills'] ?? '') ?>" oninput="updatePreview()">
                </div>

                <!-- Projects Section -->
                <div class="form-group">
                    <label style="font-weight: 700; color: var(--ump-navy); font-size: 1rem; border-bottom: 1px solid #cbd5e1; padding-bottom: 0.2rem;">Projects</label>
                    <div id="projects-container">
                        <?php foreach (($cv_data['projects'] ?? []) as $i => $proj): ?>
                            <div class="section-box">
                                <input type="text" name="projects[<?= $i ?>][title]" class="form-control" placeholder="Project Title" value="<?= htmlspecialchars($proj['title'] ?? '') ?>" oninput="updatePreview()">
                                <input type="text" name="projects[<?= $i ?>][tech]" class="form-control" placeholder="Technologies Used (e.g., PHP, MySQL)" value="<?= htmlspecialchars($proj['tech'] ?? '') ?>" oninput="updatePreview()">
                                <textarea name="projects[<?= $i ?>][description]" class="form-control" placeholder="Project Description" oninput="updatePreview()"><?= htmlspecialchars($proj['description'] ?? '') ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Work Experience Section -->
                <div class="form-group">
                    <label style="font-weight: 700; color: var(--ump-navy); font-size: 1rem; border-bottom: 1px solid #cbd5e1; padding-bottom: 0.2rem;">Work Experience / Internships</label>
                    <div id="experience-container">
                        <?php foreach (($cv_data['experience'] ?? []) as $i => $exp): ?>
                            <div class="section-box">
                                <input type="text" name="experience[<?= $i ?>][company]" class="form-control" placeholder="Company / Organization" value="<?= htmlspecialchars($exp['company'] ?? '') ?>" oninput="updatePreview()">
                                <input type="text" name="experience[<?= $i ?>][role]" class="form-control" placeholder="Role / Position" value="<?= htmlspecialchars($exp['role'] ?? '') ?>" oninput="updatePreview()">
                                <input type="text" name="experience[<?= $i ?>][period]" class="form-control" placeholder="Period (e.g., Jan 2025 - Dec 2025)" value="<?= htmlspecialchars($exp['period'] ?? '') ?>" oninput="updatePreview()">
                                <textarea name="experience[<?= $i ?>][description]" class="form-control" placeholder="Key responsibilities and achievements" oninput="updatePreview()"><?= htmlspecialchars($exp['description'] ?? '') ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Proof of registration slot is successfully removed here -->

                <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;"><i class="fa-solid fa-floppy-disk"></i> Save & Link CV to Profile</button>
            </form>
        </div>

        <!-- Real-time Preview Pane -->
        <div class="preview-pane">
            <h3 style="margin-top: 0; color: var(--ump-navy);">Live Document Preview</h3>
            
            <div id="cv-paper" class="tmpl-modern">
                <div class="cv-header">
                    <div>
                        <h2><?= htmlspecialchars($_SESSION['full_name']) ?></h2>
                        <p style="margin: 0.2rem 0; color: #64748b; font-weight: 600;"><?= htmlspecialchars($student['qualification'] ?? 'DICT') ?></p>
                    </div>
                    <div>
                        <img id="prev-img" src="<?= !empty($profile_pic) ? htmlspecialchars($profile_pic) : 'https://via.placeholder.com/80?text=Photo' ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--ump-gold);">
                    </div>
                </div>

                <div class="cv-sec-title">Professional Summary</div>
                <p id="prev-summary"><?= htmlspecialchars($cv_data['summary'] ?: 'Dedicated ICT student with a strong background in software development and database design.') ?></p>

                <div class="cv-sec-title">Skills Matrix</div>
                <p id="prev-skills"><?= htmlspecialchars($cv_data['skills'] ?: 'PHP, MySQL, JavaScript, Java, HTML/CSS') ?></p>

                <div class="cv-sec-title">Projects</div>
                <div id="prev-projects">
                    <?php foreach (($cv_data['projects'] ?? []) as $proj): ?>
                        <p><strong><?= htmlspecialchars($proj['title']) ?></strong> (<em><?= htmlspecialchars($proj['tech']) ?></em>)<br><?= htmlspecialchars($proj['description']) ?></p>
                    <?php endforeach; ?>
                </div>

                <div class="cv-sec-title">Work Experience</div>
                <div id="prev-experience">
                    <?php foreach (($cv_data['experience'] ?? []) as $exp): ?>
                        <p><strong><?= htmlspecialchars($exp['role']) ?></strong> at <?= htmlspecialchars($exp['company']) ?> (<?= htmlspecialchars($exp['period']) ?>)<br><?= htmlspecialchars($exp['description']) ?></p>
                    <?php endforeach; ?>
                </div>

                <div class="cv-sec-title">Education</div>
                <p><strong>University of Mpumalanga</strong> &mdash; <?= htmlspecialchars($student['qualification'] ?? 'DICT') ?></p>

                <div class="cv-sec-title">References</div>
                <p>Available upon request / Academic Coordinators at University of Mpumalanga.</p>
            </div>
        </div>
    </div>

    <script>
        function updatePreview() {
            document.getElementById('prev-summary').innerText = document.getElementById('input-summary').value || 'No summary provided.';
            document.getElementById('prev-skills').innerText = document.getElementById('input-skills').value || 'No skills listed.';
            
            let tmpl = document.getElementById('template-select').value;
            let paper = document.getElementById('cv-paper');
            paper.className = tmpl === 'classic' ? 'tmpl-classic' : 'tmpl-modern';
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('prev-img').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function downloadPDF() {
            let element = document.getElementById('cv-paper');
            let opt = {
                margin:       0.5,
                filename:     '<?= str_replace(' ', '_', $_SESSION['full_name']) ?>_CV.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>