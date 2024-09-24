jQuery(document).ready(function ($) {
  // Función para cargar los datos del informe de salud
  function loadHealthReport() {
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "get_health_report",
      },
      success: function (response) {
        if (response.success) {
          displayHealthReport(response.data);
        } else {
          alert("Error al cargar el informe de salud.");
        }
      },
      error: function () {
        alert("Error de conexión al cargar el informe de salud.");
      },
    });
  }

  // Función para mostrar el informe de salud en el dashboard
  function displayHealthReport(data) {
    var $dashboard = $("#wp-health-checker-dashboard");
    $dashboard.empty();

    // Rendimiento del sitio web
    var $performance = $('<div class="wp-health-checker-section"></div>');
    $performance.append("<h2>Rendimiento del Sitio Web</h2>");
    $performance.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Tiempo de carga estimado:</span> ' +
        data.load_time +
        " segundos</div>"
    );
    $performance.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Tamaño de la página principal:</span> ' +
        data.page_size +
        " KB</div>"
    );
    $dashboard.append($performance);

    // Estado del sistema
    var $system = $('<div class="wp-health-checker-section"></div>');
    $system.append("<h2>Estado del Sistema</h2>");
    $system.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Versión de WordPress:</span> ' +
        data.wp_version +
        "</div>"
    );
    $system.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Versión de PHP:</span> ' +
        data.php_version +
        "</div>"
    );
    $system.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Espacio en disco:</span> ' +
        data.disk_space +
        "</div>"
    );
    $system.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Plugins activos:</span> ' +
        data.active_plugins +
        "</div>"
    );
    $system.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Plugins inactivos:</span> ' +
        data.inactive_plugins +
        "</div>"
    );
    $system.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Tema activo:</span> ' +
        data.active_theme +
        "</div>"
    );
    $system.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Actualizaciones pendientes:</span> ' +
        data.pending_updates +
        "</div>"
    );
    $dashboard.append($system);

    // Seguridad básica
    var $security = $('<div class="wp-health-checker-section"></div>');
    $security.append("<h2>Seguridad Básica</h2>");
    $security.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Intentos fallidos de inicio de sesión:</span> ' +
        data.failed_logins +
        "</div>"
    );
    $security.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Archivos críticos modificados:</span> ' +
        data.modified_files +
        "</div>"
    );
    $dashboard.append($security);

    // Monitoreo de comentarios
    var $comments = $('<div class="wp-health-checker-section"></div>');
    $comments.append("<h2>Monitoreo de Comentarios</h2>");
    $comments.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Comentarios pendientes:</span> ' +
        data.pending_comments +
        "</div>"
    );
    $comments.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Comentarios spam:</span> ' +
        data.spam_comments +
        "</div>"
    );
    $dashboard.append($comments);

    // SEO básico
    var $seo = $('<div class="wp-health-checker-section"></div>');
    $seo.append("<h2>SEO Básico</h2>");
    $seo.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Título de la página principal:</span> ' +
        data.home_title +
        "</div>"
    );
    $seo.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Descripción de la página principal:</span> ' +
        data.home_description +
        "</div>"
    );
    $seo.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Errores 404 internos recientes:</span> ' +
        data.internal_404s +
        "</div>"
    );
    $dashboard.append($seo);

    // E-commerce (si WooCommerce está instalado)
    if (data.woocommerce) {
      var $woocommerce = $('<div class="wp-health-checker-section"></div>');
      $woocommerce.append("<h2>E-commerce (WooCommerce)</h2>");
      $woocommerce.append(
        '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Pedidos recientes (últimos 7 días):</span> ' +
          data.recent_orders +
          "</div>"
      );
      $woocommerce.append(
        '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Productos con stock bajo:</span> ' +
          data.low_stock_products +
          "</div>"
      );
      $dashboard.append($woocommerce);
    }

    // Accesibilidad y usabilidad básica
    var $accessibility = $('<div class="wp-health-checker-section"></div>');
    $accessibility.append("<h2>Accesibilidad y Usabilidad Básica</h2>");
    $accessibility.append(
      '<div class="wp-health-checker-item"><span class="wp-health-checker-item-label">Enlaces rotos en la página principal:</span> ' +
        data.broken_links +
        "</div>"
    );
    $dashboard.append($accessibility);
  }

  // Cargar el informe de salud al cargar la página
  loadHealthReport();

  // Configuración de correos electrónicos de administrador
  $("#wp-health-checker-add-email").on("click", function (e) {
    e.preventDefault();
    var email = $("#wp-health-checker-new-email").val();
    if (email) {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "add_admin_email",
          email: email,
        },
        success: function (response) {
          if (response.success) {
            loadAdminEmails();
            $("#wp-health-checker-new-email").val("");
          } else {
            alert("Error al agregar el correo electrónico.");
          }
        },
        error: function () {
          alert("Error de conexión al agregar el correo electrónico.");
        },
      });
    }
  });

  // Función para cargar los correos electrónicos de administrador
  function loadAdminEmails() {
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "get_admin_emails",
      },
      success: function (response) {
        if (response.success) {
          displayAdminEmails(response.data);
        } else {
          alert("Error al cargar los correos electrónicos de administrador.");
        }
      },
      error: function () {
        alert(
          "Error de conexión al cargar los correos electrónicos de administrador."
        );
      },
    });
  }

  // Función para mostrar los correos electrónicos de administrador
  function displayAdminEmails(emails) {
    var $list = $("#wp-health-checker-admin-emails");
    $list.empty();
    emails.forEach(function (email) {
      $list.append(
        "<li>" +
          email +
          ' <button class="wp-health-checker-remove-email" data-email="' +
          email +
          '">Eliminar</button></li>'
      );
    });
  }

  // Eliminar correo electrónico de administrador
  $(document).on("click", ".wp-health-checker-remove-email", function () {
    var email = $(this).data("email");
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "remove_admin_email",
        email: email,
      },
      success: function (response) {
        if (response.success) {
          loadAdminEmails();
        } else {
          alert("Error al eliminar el correo electrónico.");
        }
      },
      error: function () {
        alert("Error de conexión al eliminar el correo electrónico.");
      },
    });
  });

  // Cargar los correos electrónicos de administrador al cargar la página
  loadAdminEmails();

  // Enviar correo de prueba
  $("#wp-health-checker-test-email").on("click", function (e) {
    e.preventDefault();
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "send_test_email",
      },
      beforeSend: function () {
        $(this).text("Enviando...").prop("disabled", true);
      },
      success: function (response) {
        if (response.success) {
          alert(response.data);
        } else {
          alert("Error: " + response.data);
        }
      },
      error: function () {
        alert("Error de conexión al enviar el correo de prueba.");
      },
      complete: function () {
        $("#wp-health-checker-test-email")
          .text("Enviar Correo de Prueba")
          .prop("disabled", false);
      },
    });
  });
});
