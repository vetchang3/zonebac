(function ($) {
  "use strict";

  $(function () {
    // 1. Gestion des Selects Hiérarchiques
    const selects = {
      classe: document.getElementById("zb-classe"),
      matiere: document.getElementById("zb-matiere"),
      chapitre: document.getElementById("zb-chapitre"),
      notion: document.getElementById("zb-notion"),
    };
    const btnSubmit = document.getElementById("submit-gen");

    if (selects.classe) {
    async function updateSelect(type, parentId, nextSelect, following = []) {
      nextSelect.disabled = true;
      nextSelect.innerHTML = "<option>Chargement...</option>";
      following.forEach((s) => {
        s.disabled = true;
        s.innerHTML = '<option value="">-- Attente --</option>';
      });

      try {
        const response = await fetch(
          `${zbData.rest_url}zonebac/v1/get-hierarchy/?type=${type}&parent_id=${parentId}`,
        );
        const data = await response.json();
        nextSelect.innerHTML = '<option value="">-- Sélectionner --</option>';
        if (Array.isArray(data)) {
          data.forEach((item) => {
            nextSelect.innerHTML += `<option value="${item.id}">${item.name}</option>`;
          });
          nextSelect.disabled = false;
        }
      } catch (e) {
          console.error("Zonebac Error:", e);
      }
    }

    selects.classe.addEventListener("change", () =>
      updateSelect("matiere", selects.classe.value, selects.matiere, [
        selects.chapitre,
        selects.notion,
      ]),
    );
    selects.matiere.addEventListener("change", () =>
      updateSelect("chapitre", selects.matiere.value, selects.chapitre, [
        selects.notion,
      ]),
    );
    selects.chapitre.addEventListener("change", () =>
      updateSelect("notion", selects.chapitre.value, selects.notion),
    );
    }

    // 2. Gestion du bouton d'analyse IA (Optimisé pour retour immédiat)
    $(document).on("click", ".zb-btn-analyze", function (e) {
      e.preventDefault();

      const fileId = $(this).data("file-id");
      const fullZone = $('#zb-full-width-test');
      const contentArea = $('#zb-preview-content');

      // ÉTAPE 1 : Affichage immédiat du conteneur avec l'animation
      fullZone.show(); 
      contentArea.html(
        '<div style="padding:100px; text-align:center; color:#38bdf8;">' +
          '<span class="dashicons dashicons-update spin" style="font-size:50px; width:50px; height:50px;"></span>' +
          '<h2 style="color:#38bdf8; margin-top:20px;">Analyse sémantique et Composition des exercices...</h2>' +
          '<p style="color:#94a3b8;">L\'IA identifie les sections et génère 10 questions par exercice. Cela peut prendre jusqu\'à 60 secondes.</p>' +
        '</div>'
      );

      // ÉTAPE 2 : Scroll automatique vers la zone pour que l'utilisateur voie que ça bouge
      $('html, body').animate({
          scrollTop: fullZone.offset().top - 100
      }, 500);

      // ÉTAPE 3 : Lancement de l'appel AJAX
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "zb_debug_analyze_step_1",
          file_id: fileId,
          nonce: zbData.nonce,
        },
        success: function (response) {
            if (response.success) {
                console.log("ZB DEBUG DATA RECEIVED");
                
                // Injection du résultat final
                contentArea.html(response.data.html);

                // Relance MathJax
                let checkMathJax = setInterval(function() {
                    if (window.MathJax && window.MathJax.typesetPromise) {
                        clearInterval(checkMathJax);
                        window.MathJax.typesetPromise([contentArea[0]])
                            .catch((err) => { console.error("Erreur MathJax:", err); });
                    }
                }, 300);
                setTimeout(() => clearInterval(checkMathJax), 5000);
            }
        },
        error: function (xhr) {
          contentArea.html(
            '<div class="notice notice-error" style="padding:20px;">' +
            '<h3>Erreur critique</h3>' +
            '<p>Le serveur a mis trop de temps à répondre ou une erreur PHP est survenue.</p>' +
            '</div>'
          );
        },
      });
    });

    // Dans admin-generator.js
    $(document).on('click', '.btn-generate-ex', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const sectionIndex = $btn.data('section-id');
        const $card = $btn.closest('.zb-section-card');

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Génération...');

        $.ajax({
            url: zbData.rest_url + 'zonebac/v1/generate-single-exercise',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', zbData.nonce);
            },
            data: {
                file_id: $('#zb_file_id').val(), // Assure-toi d'avoir cet ID dans ta vue
                section_index: sectionIndex
            },
            success: function(response) {
                if (response.success) {
                    $card.append('<div style="padding:15px; background:#064e3b; color:#10b981; border-top:1px solid #065f46;">✅ Exercice généré et ajouté à la banque !</div>');
                    $btn.remove(); // On enlève le bouton après succès
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Réessayer');
                alert('Erreur lors de la génération.');
            }
        });
    });



  });


})(jQuery);
