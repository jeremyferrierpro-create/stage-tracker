// Point d'entrée JavaScript (T-011).
// Les composants interactifs (Livewire/Alpine) seront branchés ici à partir de
// T-020 (décision DEC-ST-014 sur la version Livewire en attente).
document.querySelectorAll('[data-flash-dismiss]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('.flash').remove();
    });
});
