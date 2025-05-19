// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips everywhere
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Enable popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Add active class to current nav item based on URL
    const currentLocation = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        if (currentLocation.includes(linkHref) && linkHref !== 'index.php') {
            link.classList.add('active');
            document.querySelector('.nav-link[href="index.php"]').classList.remove('active');
        } else if (currentLocation.endsWith('/') || currentLocation.endsWith('index.php')) {
            document.querySelector('.nav-link[href="index.php"]').classList.add('active');
        }
    });
});