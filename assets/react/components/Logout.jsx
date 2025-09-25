import apiService from "@services/apiService";

const Logout = () => {
    const handleLogout = () => {
        try {
            localStorage.removeItem('authToken');
            apiService.logout();
        } catch (error) {
            console.error('Error during logout:', error);
            window.location.href = '/login';
        }
    };

    return (
        <button onClick={handleLogout} className="btn btn-neutral btn-block">
            Logout
        </button>
    );
}

export default Logout;