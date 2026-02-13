// Load members
document.addEventListener('DOMContentLoaded', function() {
    fetch('users.json')
        .then(response => response.json())
        .then(users => {
            const membersDiv = document.getElementById('members');
            users.forEach(user => {
                if (user.verified) {
                    const memberDiv = document.createElement('div');
                    memberDiv.className = 'member';
                    memberDiv.innerHTML = `
                        <img src="https://www.habbo.es/habbo-imaging/avatarimage?user=${encodeURIComponent(user.habbo_username)}" alt="Avatar">
                        <p>${user.username}</p>
                        <p>${user.habbo_username}</p>
                        <p>Verificado: ${user.verified ? 'Sí' : 'No'}</p>
                    `;
                    membersDiv.appendChild(memberDiv);
                }
            });
        })
        .catch(error => console.error('Error loading members:', error));
});
