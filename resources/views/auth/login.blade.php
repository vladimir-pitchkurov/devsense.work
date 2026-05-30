<x-layout title="Sign In | DevSense" description="Access the authoring dashboard to manage articles and guides">
<main class="login-page">
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">Sign In</h1>
            <p class="login-subtitle">Access the authoring dashboard to manage articles and guides</p>

            <form action="{{ route('login.post') }}" method="POST" class="login-form">
                @csrf

                @if ($errors->any())
                    <div class="login-error-alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus 
                        placeholder="you@example.com"
                        class="form-input @error('email') is-invalid @enderror"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        required 
                        placeholder="••••••••"
                        class="form-input @error('password') is-invalid @enderror"
                    >
                </div>

                <button type="submit" class="login-button">
                    Continue to Dashboard
                </button>
            </form>
        </div>
    </div>
</main>

<style>
.login-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 12rem);
    padding: 2rem;
}

.login-container {
    width: 100%;
    max-width: 440px;
}

.login-card {
    background-color: var(--card-bg, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--border-color);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 1rem;
    padding: 2.5rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.login-title {
    font-family: 'Outfit', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    text-align: center;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.login-subtitle {
    font-size: 0.95rem;
    color: var(--text-muted);
    text-align: center;
    margin-bottom: 2rem;
    line-height: 1.5;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-color);
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.form-input {
    background-color: rgba(var(--bg-color-rgb), 0.5);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    color: var(--text-color);
    font-family: inherit;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.15);
}

.login-button {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: #fff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.85rem;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: transform 0.2s, opacity 0.2s;
    margin-top: 0.5rem;
}

.login-button:hover {
    transform: translateY(-1px);
    opacity: 0.95;
}

.login-button:active {
    transform: translateY(0);
}

.login-error-alert {
    background-color: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.9rem;
}

.login-error-alert ul {
    margin: 0;
    padding-left: 1.25rem;
}
</style>
</x-layout>
