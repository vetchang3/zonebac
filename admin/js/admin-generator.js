(function ($) {
  "use strict";

  $(function () {
    const selects = {
      classe: document.getElementById("zb-classe"),
      matiere: document.getElementById("zb-matiere"),
      chapitre: document.getElementById("zb-chapitre"),
      notion: document.getElementById("zb-notion"),
    };
    const btn = document.getElementById("submit-gen");

    if (!selects.classe) return;

    async function updateSelect(type, parentId, nextSelect, following = []) {
      nextSelect.disabled = true;
      nextSelect.innerHTML = "<option>Chargement...</option>";
      following.forEach((s) => {
        s.disabled = true;
        s.innerHTML = '<option value="">-- Attente --</option>';
      });
      btn.disabled = true;

      try {
        // Utilisation de la variable localisée via wp_localize_script
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
        console.error("Zonebac API Error:", e);
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
    selects.notion.addEventListener("change", () => {
      btn.disabled = !selects.notion.value;
    });
  });
})(jQuery);
