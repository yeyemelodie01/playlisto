import {Link, useLocation} from "react-router-dom";
import { useState, useEffect } from "react";
import apiService from "@services/apiService";

const Header = () => {
    const [me, setMe] = useState(null);
    const [loading, setLoading] = useState(true);
    const location = useLocation();

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

    const hidePlaylistLink = ["/history", "/profile"].some(path => location.pathname.startsWith(path));

    return (
        <header className="navbar">
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
                                        <Link to="/profile" className="text-base text-black font-bold">Bienvenue {displayName}</Link>
                                    </li>
                                    <li>
                                        <Link to="/favorites" className="text-base text-black font-bold">Mes favoris</Link>
                                    </li>
                                    {!hidePlaylistLink && (
                                        <li>
                                            <Link to="/history" className="text-base text-black font-bold">Historique</Link>
                                        </li>
                                    )}
                                    <li>
                                        <Link className="text-base text-black font-bold" onClick={handleLogout}>Se déconnecter</Link>
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
                                    <Link to="/profile" className="text-base text-white font-bold">Bienvenue {displayName}</Link>
                                </li>
                                {!hidePlaylistLink && (
                                    <li>
                                        <Link to="/history" className="text-base text-white font-bold">Historique</Link>
                                    </li>
                                )}
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