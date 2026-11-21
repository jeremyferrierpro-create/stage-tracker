// Point d'entrée JavaScript (T-011).
// Les composants interactifs (Livewire/Alpine) seront branchés ici à partir de
// T-020 (décision DEC-ST-014 sur la version Livewire en attente).
document.querySelectorAll('[data-flash-dismiss]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('.flash').remove();
    });
});

// Messagerie : démarre la lecture en bas du fil et recharge toutes les 15 s
// tant que la page de conversation est ouverte et qu'aucun champ n'est édité.
document.querySelectorAll('[data-chat-poll]').forEach((chat) => {
    const thread = chat.querySelector('.chat-thread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    setInterval(() => {
        const active = document.activeElement;
        const editing = active && (active.tagName === 'TEXTAREA' || active.tagName === 'INPUT');
        if (!editing) window.location.reload();
    }, 15000);
});
