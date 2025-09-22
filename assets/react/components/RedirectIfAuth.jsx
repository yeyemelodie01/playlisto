// components/RedirectIfAuth.jsx
import { Navigate, useLocation } from 'react-router-dom';
import apiService from '../services/apiService.js';

export default function RedirectIfAuth({ children }) {
    const token = apiService.getToken();
    const location = useLocation();

    if (token) {
        return <Navigate to="/teams" replace />;
    }

    return children;
}