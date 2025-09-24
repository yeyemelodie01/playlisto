import React from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import Login from "@pages/account/Login";
import RedirectIfAuth from "@components/RedirectIfAuth";
import Home from "@pages/home/Home";
import ProtectedRoute from "@components/ProtectedRoute";
import Question from "@pages/question/Question";
import Playlist from "@pages/playlist/Playlist";

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
        <Route path="/question" element={ <Question /> }/>
        <Route path="/playlist" element={ <Playlist /> } />
    </Routes>
  );
};

export default AppRoutes;