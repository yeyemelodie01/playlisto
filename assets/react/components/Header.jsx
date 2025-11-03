import { Link } from "react-router-dom";
import { useState, useEffect } from "react";
import apiService from "@services/apiService";

const Header = () => {
    const [me, setMe] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const token = apiService.getToken();
        if (!token) {
            setLoading(false);
            return;
        }

        apiService.get("/api/me")
            .then(res => setMe(res.data || null))
            .catch(() => setMe(null))
            .finally(() => setLoading(false));
    }, []);

    const displayName = (me && (me.username || (me.email ? me.email.split("@")[0] : ""))) || "";

    const handleLogout = async () => {
        await apiService.logout();
    };

    return (
        <header className="navbar py-2">
            <div className="navbar">
                <div className="navbar-start">
                    <div className="dropdown">
                        <div tabIndex={0} role="button" className="btn btn-ghost lg:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h7" /> </svg>
                        </div>
                        <ul tabIndex="-1" className="menu menu-sm dropdown-content bg-base-100 rounded-box z-1 mt-3 w-52 p-2 shadow">
                            {!loading && me && (
                                <>
                                    <li>
                                        <p className="text-base text-white font-bold">Bienvenue {displayName}</p>
                                    </li>
                                    <li className="disabled">
                                        <Link to="/profile" className="text-base text-white font-bold" onClick={(e) => e.preventDefault()}>Profil (bientôt)</Link>
                                    </li>
                                    <li>
                                        <Link className="text-base text-white font-bold" onClick={handleLogout}>Se déconnecter</Link>
                                    </li>
                                </>
                            )}
                        </ul>
                    </div>
                    <Link to="/">
                        <img src="/images/playlisto-logo.png" alt="playlisto logo" className="w-40 px-4 py-2"/>
                    </Link>
                </div>
                <div className="navbar-end hidden lg:flex">
                    <ul className="menu menu-horizontal px-1">
                        {!loading && me && (
                            <>
                                <li>
                                    <p className="text-base text-white font-bold">Bienvenue {displayName}</p>
                                </li>
                                <li className="disabled">
                                    <Link to="/profile" onClick={(e) => e.preventDefault()} className="text-base font-bold text-white">Profil (bientôt)</Link>
                                </li>
                                <li>
                                    <Link onClick={handleLogout} className="font-bold text-white text-base">Se déconnecter</Link>
                                </li>
                            </>
                        )}
                    </ul>
                </div>
            </div>
        </header>
    )
}

export default Header;