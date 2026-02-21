  <!-- Vendor JS Files -->
  <?php require_once __DIR__ . '/scripts.php'; ?>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
          class="bi bi-arrow-up-short"></i></a>

  <!-- Bootstrap Form Validation -->
  <script>
      // Bootstrap form validation
      (function() {
          'use strict'
          var forms = document.querySelectorAll('.needs-validation')
          Array.prototype.slice.call(forms)
              .forEach(function(form) {
                  form.addEventListener('submit', function(event) {
                      if (!form.checkValidity()) {
                          event.preventDefault()
                          event.stopPropagation()
                      }
                      form.classList.add('was-validated')
                  }, false)
              })
      })()
  </script>