<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Layout Footer Component
 */
?>
<?php if (is_logged_in()): ?>
        </main>
        <!-- App Content Footer -->
        <footer class="bg-white border-top py-3 px-4 text-center text-sm-start d-sm-flex justify-content-between align-items-center mt-auto">
            <div class="text-muted small">
                &copy; <?= date('Y') ?> <strong>Kabarak University</strong>. All rights reserved.
            </div>
            <div class="text-muted small mt-2 mt-sm-0">
                <span class="badge bg-light text-secondary border">Academic Consultations</span>
                <span class="mx-2">&bull;</span>
                <a href="mailto:support@kabarak.ac.ke" class="text-decoration-none text-muted"><i class="bi bi-envelope me-1"></i>Helpdesk</a>
            </div>
        </footer>
    </div><!-- /.app-content -->
</div><!-- /.app-wrapper -->
<?php else: ?>
    </div><!-- /.container public -->
    <!-- Public Footer -->
    <footer class="bg-navy-dark text-white py-4 mt-auto border-top border-warning">
        <div class="container">
            <div class="row gy-3 align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                        <i class="bi bi-mortarboard-fill text-gold fs-5"></i>
                        <span class="fw-bold">KABARAK UNIVERSITY</span>
                    </div>
                    <p class="small text-white-50 mb-0">Empowering education and academic mentorship through seamless consultation scheduling.</p>
                </div>
                <div class="col-md-6 text-center text-md-end text-white-50 small">
                    <div>Main Campus, Nakuru-Ravine Road, Kenya</div>
                    <div>Inquiries: <a href="mailto:consult@kabarak.ac.ke" class="text-gold text-decoration-none">consult@kabarak.ac.ke</a> | +254 700 000 000</div>
                </div>
            </div>
            <hr class="border-secondary my-3">
            <div class="text-center text-white-50 small">
                &copy; <?= date('Y') ?> Kabarak University Consultation Booking System. Built for students and faculty.
            </div>
        </div>
    </footer>
<?php endif; ?>

<!-- Bootstrap 5.3 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Custom App JS -->
<script src="<?= url('public/assets/js/main.js') ?>"></script>
</body>
</html>
