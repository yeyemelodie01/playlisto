import React, { createContext, useContext, useEffect, useState, useCallback } from "react";
import api from "@services/apiService";

const AuthCtx = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchMe = useCallback(async () => {
        try {
            const { data } = await api.get("/api/me");
            setUser(data);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { fetchMe(); }, [fetchMe]);

    const login = useCallback(async (email, password) => {
        await api.post("/api/authentication_token", { email, password });
        await fetchMe();
    }, [fetchMe]);

    const logout = useCallback(async () => {
        try { await api.post("/api/logout"); } catch {}
        setUser(null);
        window.location.assign("/login");
    }, []);

    const value = { user, loading, login, logout, refresh: fetchMe, isAuthenticated: !!user };
    return <AuthCtx.Provider value={value}>{children}</AuthCtx.Provider>;
}

export function useAuth() {
    const ctx = useContext(AuthCtx);
    if (!ctx) throw new Error("useAuth must be used within <AuthProvider>");
    return ctx;
}