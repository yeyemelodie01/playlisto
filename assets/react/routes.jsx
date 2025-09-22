import React from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import Login from "./pages/account/Login.jsx";
import RedirectIfAuth from "./components/RedirectIfAuth.jsx";
import Home from "./pages/home/Home.jsx";
import ProtectedRoute from "./components/ProtectedRoute";

const AppRoutes = () => {
  return (
    <Routes>
        <Route path="/" element={<Navigate to="/login" replace />} />
        <Route
            path="/login"
            element={
                <RedirectIfAuth>
                    <Login />
                </RedirectIfAuth>
            }
        />
        <Route path="/home"
               element={
                <ProtectedRoute>
                    <Home />
                </ProtectedRoute>
            }
        />
    </Routes>
  );
};

export default AppRoutes;