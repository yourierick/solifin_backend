document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour gérer les menus déroulants
    function setupDropdown(buttonId, menuId) {
        const button = document.getElementById(buttonId);
        const menu = document.getElementById(menuId);

        if (!button || !menu) return;

        // Gérer le clic sur le bouton
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = menu.classList.contains('hidden');
            
            // Fermer tous les autres menus
            document.querySelectorAll('[id$="-menu"]').forEach(m => {
                if (m.id !== menuId) {
                    m.classList.add('hidden');
                }
            });

            // Basculer le menu actuel
            menu.classList.toggle('hidden');
            
            // Mettre à jour l'attribut aria-expanded
            button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
    }

    // Fermer les menus au clic en dehors
    document.addEventListener('click', (e) => {
        const menus = document.querySelectorAll('[id$="-menu"]');
        menus.forEach(menu => {
            if (!menu.contains(e.target)) {
                menu.classList.add('hidden');
                // Mettre à jour l'attribut aria-expanded du bouton associé
                const buttonId = menu.id.replace('-menu', '-button');
                const button = document.getElementById(buttonId);
                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                }
            }
        });
    });

    // Initialiser tous les menus déroulants
    setupDropdown('user-menu-button', 'user-menu');
    setupDropdown('notification-menu-button', 'notification-menu');
    setupDropdown('admin-menu-button', 'admin-menu');
    setupDropdown('mobile-menu-button', 'mobile-menu');
}); 