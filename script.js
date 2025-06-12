// script.js

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle functionality
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Optional: Close mobile menu when a link is clicked
    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (!mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
            }
        });
    });

    // Form validation (example for careers.php - conceptual as PHP handles submission)
    // You would typically add more robust client-side validation here
    const careerForm = document.querySelector('form[action="process_application.php"]');
    if (careerForm) {
        careerForm.addEventListener('submit', function(event) {
            // Simple validation example: check if full name is not empty
            const fullNameInput = document.getElementById('fullName');
            if (fullNameInput && fullNameInput.value.trim() === '') {
                // In a real application, you'd show an error message on the page,
                // not an alert. This is illustrative for conceptual client-side validation.
                console.error('Please enter your full name.');
                event.preventDefault(); // Prevent form submission
                // You could add a modal or an inline error message here
                showNotification('Please fill in all required fields.', 'error');
            }
            // Add more validation rules as needed for other fields
        });
    }

    const contactForm = document.querySelector('form[action="process_contact.php"]');
    if (contactForm) {
        contactForm.addEventListener('submit', function(event) {
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const messageInput = document.getElementById('message');

            if (nameInput && nameInput.value.trim() === '') {
                console.error('Please enter your name.');
                event.preventDefault();
                showNotification('Please enter your name.', 'error');
            }
            if (emailInput && emailInput.value.trim() === '' || !emailInput.value.includes('@')) {
                console.error('Please enter a valid email address.');
                event.preventDefault();
                showNotification('Please enter a valid email address.', 'error');
            }
            if (messageInput && messageInput.value.trim() === '') {
                console.error('Please enter your message.');
                event.preventDefault();
                showNotification('Please enter your message.', 'error');
            }
        });
    }

    // Function to show a simple notification message (replaces alert())
    function showNotification(message, type = 'info') {
        const notificationContainer = document.createElement('div');
        notificationContainer.classList.add('fixed', 'bottom-4', 'right-4', 'p-4', 'rounded-lg', 'shadow-lg', 'text-white', 'z-50');

        if (type === 'error') {
            notificationContainer.classList.add('bg-red-600');
        } else if (type === 'success') {
            notificationContainer.classList.add('bg-green-600');
        } else {
            notificationContainer.classList.add('bg-blue-600');
        }

        notificationContainer.textContent = message;
        document.body.appendChild(notificationContainer);

        // Automatically remove the notification after 3 seconds
        setTimeout(() => {
            notificationContainer.remove();
        }, 3000);
    }
});
