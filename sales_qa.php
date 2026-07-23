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
    .answer-card { border-left: 3px solid #0d6efd; }
    .question-meta, .answer-meta { font-size: 0.8rem; color: #6c757d; }
    #questionsTable tbody tr { cursor: pointer; }
</style>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1><i class="bi bi-patch-question-fill"></i> Forum Q&A Sales</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal"><i class="bi bi-plus-circle"></i> Buat Pertanyaan</button>
</div>
<p class="lead text-muted mb-4"><strong>Dari Sales untuk Sales</strong>. Ini adalah tempat kita berbagi pengetahuan dan tumbuh bersama. Setiap jawaban adalah langkah menuju kesuksesan tim.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Pertanyaan</span>
        <input type="text" id="liveSearchInput" class="form-control w-50" placeholder="Cari pertanyaan, jawaban, atau nama sales...">
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="questionsTable">
                <thead class="table-light">
                    <tr>
                        <th>Pertanyaan</th>
                        <th>Detail Pertanyaan</th>
                        <th>Ditanyakan Oleh</th>
                        <th class="text-center">Jumlah Jawaban</th>
                        <th>Dibuat</th>
                        <th style="width: 5%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questions)): ?>
                        <tr><td colspan="5" class="text-center p-5"><h5>Belum ada pertanyaan. Jadilah yang pertama!</h5></td></tr>
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
                            <strong><?php echo htmlspecialchars($q['title']); ?></strong>
                        </td>
                        
                        <td class="question-data" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <?php echo htmlspecialchars($q['body']); ?>
                        </td>
                        
                        <td class="question-data" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <?php echo htmlspecialchars($q['author']); ?>
                        </td>
                        <td class="text-center question-data" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <span class="badge bg-primary rounded-pill">Lihat <?php echo count($q['answers']); ?> Jawaban</span>
                        </td>
                        <td class="question-data" data-bs-toggle="modal" data-bs-target="#viewQuestionModal">
                            <?php echo date('d M Y', strtotime($q['created_at'])); ?>
                        </td>
                        <td class="text-center">
                            <?php if ($_SESSION['user_id'] == $q['author_id'] || $_SESSION['role'] === 'superadmin'): ?>
                                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $q['id']; ?>" data-type="question" title="Hapus Pertanyaan">
                                    <i class="bi bi-trash"></i>
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

<div class="modal fade" id="viewQuestionModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="q-modal-title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <p class="mb-1" id="q-modal-body"></p>
            <small class="question-meta">Ditanyakan oleh <span id="q-modal-author"></span> pada <span id="q-modal-date"></span></small>
        </div>
        <hr>
        <h6 class="mb-3">Jawaban</h6>
        <div id="q-modal-answers-list"></div>
        <form class="add-answer-form mt-4">
            <input type="hidden" id="q-modal-question-id" name="question_id">
            <div class="mb-2"><textarea name="body" class="form-control" rows="3" placeholder="Tulis jawaban Anda..." required></textarea></div>
            <button type="submit" class="btn btn-primary btn-sm">Kirim Jawaban</button>
        </form>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="addQuestionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Buat Pertanyaan Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="addQuestionForm">
          <input type="hidden" name="action" value="add_question">
          <div class="mb-3">
            <label for="q_title" class="form-label">Judul Pertanyaan</label>
            <input type="text" class="form-control" id="q_title" name="title" required>
          </div>
          <div class="mb-3">
            <label for="q_body" class="form-label">Detail Pertanyaan</label>
            <textarea class="form-control" id="q_body" name="body" rows="5"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" form="addQuestionForm" class="btn btn-primary">Kirim</button>
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

    // Event listener untuk seluruh tabel (lihat detail & hapus pertanyaan)
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
                const deleteButtonHtml = canDelete ? `<button class="btn btn-sm btn-outline-danger delete-btn" data-id="${a.id}" data-type="answer"><i class="bi bi-trash"></i></button>` : '';
                const answerDate = new Date(a.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                answersList.innerHTML += `
                    <div class="card answer-card mb-3" id="answer-${a.id}">
                        <div class="card-body">
                            <p class="card-text">${a.body.replace(/\n/g, '<br>')}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="answer-meta">Dijawab oleh ${a.author} pada ${answerDate}</small>
                                <div>${deleteButtonHtml}</div>
                            </div>
                        </div>
                    </div>`;
            });
        } else {
            answersList.innerHTML = '<p class="text-muted">Belum ada jawaban.</p>';
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

    // Event listener untuk seluruh body modal (untuk submit jawaban dan hapus jawaban)
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