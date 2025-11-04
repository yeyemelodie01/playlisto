import React from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import Login from "@pages/account/Login";
import RedirectIfAuth from "@components/RedirectIfAuth";
import Home from "@pages/home/Home";
import ProtectedRoute from "@components/ProtectedRoute";
import Question from "@pages/question/Question";
import Playlist from "@pages/playlist/Playlist";
import OnePlaylist from "@pages/playlist/OnePlaylist";
import Signin from "@pages/account/Signin";
import Profile from "@pages/profile/Profile";

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
        <Route
            path="/register"
            element={
                <RedirectIfAuth>
                    <Signin />
                </RedirectIfAuth>
            }
        />
        <Route
            path="/home"
            element={
                <ProtectedRoute>
                    <Home />
                </ProtectedRoute>
            }
        />
        <Route
            path="/profile"
            element={
                <ProtectedRoute>
                    <Profile />
                </ProtectedRoute>
            }
        />
        <Route
            path="/question"
            element={
               <ProtectedRoute>
                    <Question />
               </ProtectedRoute>
            }
        />
        <Route
            path="/playlist"
            element={
                <ProtectedRoute>
                    <Playlist />
                </ProtectedRoute>
            }
        />
        <Route
            path="/playlist/:id"
            element={
                <ProtectedRoute>
                    <OnePlaylist />
                </ProtectedRoute>
            }
        />
    </Routes>
  );
};

export default AppRoutes;