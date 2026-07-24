<?php
$page_title = 'Forum Q&A Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'sales'])) {
    header('Location: login.php');
    exit();
}

// Ambil semua pertanyaan dan jawaban dalam satu query
$sql = "
    SELECT 
        q.id as question_id, q.title, q.body as question_body, q.created_at as question_created_at,
        qs.nama_lengkap as question_author, qs.id as question_author_id,
        a.id as answer_id, a.body as answer_body, a.created_at as answer_created_at,
        ans.nama_lengkap as answer_author, ans.id as answer_author_id
    FROM qa_questions q
    JOIN sales qs ON q.sales_id = qs.id
    LEFT JOIN qa_answers a ON q.id = a.question_id AND a.deleted_at IS NULL
    LEFT JOIN sales ans ON a.sales_id = ans.id
    WHERE q.deleted_at IS NULL
    ORDER BY q.created_at DESC, a.created_at ASC
";
$result = $conn->query($sql);
$questions = [];
while ($row = $result->fetch_assoc()) {
    $qid = $row['question_id'];
    if (!isset($questions[$qid])) {
        $questions[$qid] = [
            'id' => $row['question_id'],
            'title' => $row['title'],
            'body' => $row['question_body'],
            'author' => $row['question_author'],
            'author_id' => $row['question_author_id'],
            'created_at' => $row['question_created_at'],
            'answers' => []
        ];
    }
    if ($row['answer_id']) {
        $questions[$qid]['answers'][] = [
            'id' => $row['answer_id'],
            'body' => $row['answer_body'],
            'author' => $row['answer_author'],
            'author_id' => $row['answer_author_id'],
            'created_at' => $row['answer_created_at']
        ];
    }
}
?>

<style>
.qa-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.qa-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.qa-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.qa-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.answer-card {
    border: 1.5px solid #E2E8F0;
    border-left: 4px solid #2563EB !important;
    border-radius: 14px !important;
    background: #F8FAFC;
    transition: all 0.2s ease;
}

.answer-card:hover {
    background: #FFFFFF;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}

.question-meta, .answer-meta {
    font-size: 12px;
    color: #64748B;
    font-weight: 600;
}

#questionsTable tbody tr {
    cursor: pointer;
    transition: all 0.2s ease;
}

.answer-pill-btn {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFF;
    border-radius: 30px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.sales-avatar-badge-small {
    width: 26px; height: 26px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #FFF;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    margin-right: 8px;
}
</style>

<!-- Hero Header -->
<div class="qa-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Forum Q&A Sales</span>
            </div>
            <h1 class="qa-hero-title">Forum Q&A Sales 💬</h1>
            <p class="qa-hero-subtitle">Dari Sales untuk Sales. Tempat berbagi solusi produk, penanganan customer, dan pengetahuan tim.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-primary shadow-lg" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                <i class="bi bi-plus-circle-fill"></i> Buat Pertanyaan Baru
            </button>
        </div>
    </div>
</div>

<!-- Main Card -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="bi bi-chat-left-dots-fill"></i> Daftar Pertanyaan</h5>
        <div class="position-relative style-search" style="min-width: 280px;">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="liveSearchInput" class="form-control border-start-0 ps-0" placeholder="Cari pertanyaan atau sales...">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="questionsTable">
                <thead class="table-dark-header">
                    <tr>
                        <th style="width: 22%;">Pertanyaan</th>
                        <th style="width: 32%;">Detail Pertanyaan</th>
                        <th style="width: 18%;">Ditanyakan Oleh</th>
                        <th class="text-center" style="width: 13%;">Jumlah Jawaban</th>
                        <th style="width: 10%;">Dibuat</th>
                        <th class="text-center" style="width: 5%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questions)): ?>
                        <tr><td colspan="6" class="text-center p-5 text-muted"><h5>Belum ada pertanyaan. Jadilah yang pertama bertanya!</h5></td></tr>
                    <?php endif; ?>
                    <?php foreach ($questions as $q): ?>
                    <tr class="question-row" id="question-row-<?php echo $q['id']; ?>"
                        data-question-id="<?php echo $q['id']; ?>"
                        data-title="<?php echo htmlspecialchars($q['title']); ?>"
                        data-body="<?php echo htmlspecialchars($q['body']); ?>"
                        data-author="<?php echo htmlspecialchars($q['author']); ?>"
                        data-date="<?php echo date('d M Y', strtotime($q['created_at'])); ?>"
                        data-answers='<?php echo json_encode($q['answers']); ?>'>
                        
                        <td class="question-data" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <span class="fw-bold text-dark" style="font-family:'Plus Jakarta Sans', sans-serif;">
                                <i class="bi bi-question-circle-fill text-primary me-1"></i>
                                <?php echo htmlspecialchars($q['title']); ?>
                            </span>
                        </td>
                        
                        <td class="question-data text-muted small" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <?php echo htmlspecialchars(substr($q['body'], 0, 80)) . (strlen($q['body']) > 80 ? '...' : ''); ?>
                        </td>
                        
                        <td class="question-data" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <div class="d-flex align-items-center">
                                <div class="sales-avatar-badge-small">
                                    <?php echo strtoupper(substr($q['author'], 0, 1)); ?>
                                </div>
                                <span class="fw-semibold text-dark" style="font-size:13px;"><?php echo htmlspecialchars($q['author']); ?></span>
                            </div>
                        </td>
                        <td class="text-center question-data" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <span class="answer-pill-btn">
                                <i class="bi bi-chat-right-text-fill me-1"></i> <?php echo count($q['answers']); ?> Jawaban
                            </span>
                        </td>
                        <td class="question-data small text-muted" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <?php echo date('d M Y', strtotime($q['created_at'])); ?>
                        </td>
                        <td class="text-center">
                            <?php if ($_SESSION['user_id'] == $q['author_id'] || $_SESSION['role'] === 'superadmin'): ?>
                                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $q['id']; ?>" data-type="question" title="Hapus Pertanyaan">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal View Question -->
<div class="modal fade" id="viewQuestionModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title fw-bold" id="q-modal-title" style="font-size:16px;"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="bg-light rounded-3 p-3 mb-3 border">
            <p class="mb-2 fw-semibold text-dark" id="q-modal-body" style="font-size:14.5px; line-height:1.6;"></p>
            <div class="question-meta"><i class="bi bi-person-fill text-primary"></i> Ditanyakan oleh <span id="q-modal-author" class="fw-bold text-dark"></span> pada <span id="q-modal-date"></span></div>
        </div>
        <hr class="my-3">
        <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-chat-square-text-fill text-primary me-2"></i>Jawaban Tim Sales</h6>
        <div id="q-modal-answers-list"></div>
        <form class="add-answer-form mt-4 bg-white p-3 border rounded-3">
            <input type="hidden" id="q-modal-question-id" name="question_id">
            <div class="mb-3">
                <label class="form-label">Tulis Jawaban Anda</label>
                <textarea name="body" class="form-control" rows="3" placeholder="Bantu rekan sales Anda dengan jawaban yang jelas..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send-fill"></i> Kirim Jawaban</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Add Question -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Buat Pertanyaan Baru</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="addQuestionForm">
          <input type="hidden" name="action" value="add_question">
          <div class="mb-3">
            <label for="q_title" class="form-label">Judul Pertanyaan</label>
            <input type="text" class="form-control" id="q_title" name="title" placeholder="mis. Password standar IP CAM Loewix?" required>
          </div>
          <div class="mb-3">
            <label for="q_body" class="form-label">Detail Pertanyaan</label>
            <textarea class="form-control" id="q_body" name="body" rows="4" placeholder="Jelaskan detail pertanyaan Anda..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" form="addQuestionForm" class="btn btn-primary"><i class="bi bi-send-fill"></i> Kirim Pertanyaan</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const currentUserId = <?php echo $_SESSION['user_id']; ?>;
    const isSuperAdmin = '<?php echo $_SESSION['role']; ?>' === 'superadmin';
    const questionsTable = document.getElementById('questionsTable');

    // Live Search
    document.getElementById('liveSearchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        document.querySelectorAll('#questionsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });

    // Event listener untuk seluruh tabel
    questionsTable.addEventListener('click', function(e) {
        const deleteButton = e.target.closest('.delete-btn[data-type="question"]');
        const viewTrigger = e.target.closest('.question-data');

        if (deleteButton) {
            handleDelete(deleteButton.dataset.id, 'question');
        } else if (viewTrigger) {
            const row = viewTrigger.parentElement;
            populateAndShowModal(row);
        }
    });

    // Populate dan Tampilkan Modal Lihat Pertanyaan
    const viewQuestionModal = document.getElementById('viewQuestionModal');
    function populateAndShowModal(row) {
        const answers = JSON.parse(row.dataset.answers);
        document.getElementById('q-modal-title').textContent = row.dataset.title;
        document.getElementById('q-modal-body').textContent = row.dataset.body;
        document.getElementById('q-modal-author').textContent = row.dataset.author;
        document.getElementById('q-modal-date').textContent = row.dataset.date;
        document.getElementById('q-modal-question-id').value = row.dataset.questionId;
        
        const answersList = document.getElementById('q-modal-answers-list');
        answersList.innerHTML = '';
        if (answers.length > 0) {
            answers.forEach(a => {
                const canDelete = isSuperAdmin || currentUserId == a.author_id;
                const deleteButtonHtml = canDelete ? `<button class="btn btn-sm btn-outline-danger delete-btn" data-id="${a.id}" data-type="answer"><i class="bi bi-trash-fill"></i></button>` : '';
                const answerDate = new Date(a.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                answersList.innerHTML += `
                    <div class="card answer-card mb-3" id="answer-${a.id}">
                        <div class="card-body">
                            <p class="card-text text-dark mb-2" style="font-size:14px; line-height:1.5;">${a.body.replace(/\n/g, '<br>')}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="answer-meta"><i class="bi bi-person-circle text-primary me-1"></i> Dijawab oleh ${a.author} pada ${answerDate}</small>
                                <div>${deleteButtonHtml}</div>
                            </div>
                        </div>
                    </div>`;
            });
        } else {
            answersList.innerHTML = '<p class="text-muted fst-italic p-3 text-center">Belum ada jawaban. Jadilah yang pertama memberikan solusi!</p>';
        }
    }

    // Submit Pertanyaan Baru
    document.getElementById('addQuestionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Pertanyaan Anda telah diposting.' })
                    .then(() => window.location.reload());
                } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
            });
    });

    // Event listener untuk seluruh body modal
    viewQuestionModal.addEventListener('submit', function(e) {
        if (e.target.classList.contains('add-answer-form')) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'add_answer');
            fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                   Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Jawaban Anda telah dikirim.' }).then(() => window.location.reload());
                } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
            });
        }
    });
    
    viewQuestionModal.addEventListener('click', function(e) {
        const target = e.target.closest('.delete-btn[data-type="answer"]');
        if (target) {
            handleDelete(target.dataset.id, 'answer');
        }
    });

    // Fungsi terpusat untuk menghapus
    function handleDelete(id, type) {
        Swal.fire({
            title: 'Anda yakin?', text: "Data yang dihapus tidak bisa dikembalikan!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_' + type);
                formData.append('id', id);

                fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const elementId = (type === 'question') ? 'question-row-' + id : type + '-' + id;
                        document.getElementById(elementId).remove();
                    } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
                });
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>