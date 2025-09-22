import {Navigate, useNavigate} from "react-router-dom";
import {useEffect, useState} from "react";
import Textfield from "../../components/Textfield";
import apiService from "../../services/apiService";

export default function Login() {
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [message, setMessage] = useState('');
    const [emailError, setEmailError] = useState('');
    const [passwordError, setPasswordError] = useState('');

    useEffect(() => {
        document.title = 'Login - Playlisto';

        const token = apiService.getToken();
        if (token) {
            navigate('/home');
        }
    }, [navigate]);

    const token = apiService.getToken();
    if (token) {
        return <Navigate to="/home" replace />;
    }

    const handleEmailChange = (e) => {
        const value = e.target.value;
        setEmail(value);
        if (value.length === 0) {
            setEmailError('Please provide an email.');
        } else {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!regex.test(value)) {
                setEmailError('Unfortunately, this email address seems to be wrong.');
            } else {
                setEmailError('');
            }
        }
    };

    const handlePasswordChange = (e) => {
        const value = e.target.value;
        setPassword(value);
        if (value.length === 0) {
            setPasswordError('Please provide your password.');
        } else {
            if (value.length > 0 && value.length < 8) {
                setPasswordError('Password must contain at least 8 characters.');
            } else {
                setPasswordError('');
            }
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        try {
            const response = await apiService.post('/api/authentication_token', {
                email,
                password,
            });

            if (response.data?.token) {
                apiService.setToken(response.data.token);
                setMessage('Login successful! Redirecting...');
                setTimeout(() => {
                    navigate('/home');
                }, 1500);
            } else {
                setMessage('Login failed. Please try again.');
            }
        } catch (error) {
            setMessage('An error occurred during login. Please try again later.');
            console.error('Login error:', error);
        }
    };

    return (
        <main className="min-h-screen flex items-center justify-center bg-base-200">
            <div className="form-width flex flex-col items-center">
                <img src="/images/playlisto-logo.png" alt="Logo" className="w-64 px-4 py-2"/>
                <h1 className="text-xl text-center my-4">Log in</h1>
                <div className="flex flex-col gap-6 mb-4">
                    <h3>Spotify Log in</h3>
                </div>
                <div className="flex flex-row gap-2 items-center">
                    <span className="block h-px grow bg-neutral-800"></span>
                    <span className="text-xs text-terciary schrink">or</span>
                    <span className="block h-px grow bg-neutral-800"></span>
                </div>
                <form onSubmit={handleSubmit} method="post" className="form-login w-80">
                    <Textfield
                        type="email"
                        placeholder="Email"
                        size="md"
                        value={email}
                        onChange={handleEmailChange}
                        isError={emailError}
                        errorCaption={emailError}
                        required
                    />
                    <span id="email_error_message" className="text-red-500"></span>
                    <Textfield
                        type="password"
                        placeholder="Password"
                        size="md"
                        value={password}
                        onChange={handlePasswordChange}
                        isError={passwordError}
                        errorCaption={passwordError}
                        required
                    />
                    <span id="password_error_message" className="text-red-500"></span>
                    <div className="grid">
                        <button
                            type="submit"
                            className="mt-5 bg-black text-white px-4 py-2 rounded-md">
                            Log in
                        </button>
                    </div>
                </form>
            </div>
        </main>


    );
}