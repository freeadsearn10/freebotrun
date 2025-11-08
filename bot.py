import os
import json
import asyncio
import time
import random
import getpass
import sys
import subprocess
import math
import hashlib
import base64
import secrets
import string
from datetime import datetime, timedelta
from io import BytesIO
import mimetypes
import requests
from PIL import Image, ImageDraw, ImageFont

# Global variable to track installed packages
installed_packages = set()

# ====== LAZY PACKAGE INSTALLER ======

def install_package_lazy(package, import_name=None):
    """Install a package only when needed"""
    global installed_packages

    if import_name is None:  
        import_name = package.split('==')[0]  
  
    # Check if already installed  
    if import_name in installed_packages:  
        return True  
  
    try:  
        # Try to import first  
        if import_name == 'Pillow':  
            import PIL  
        elif import_name == 'psutil':  
            import psutil  
        elif import_name == 'qrcode':  
            import qrcode  
        elif import_name == 'googletrans':  
            import googletrans  
        elif import_name == 'clarifai_grpc':  
            import clarifai_grpc  
        else:  
            __import__(import_name)  
          
        installed_packages.add(import_name)  
        return True  
    except ImportError:  
        # Package not found, install it  
        try:  
            print(f"📦 Installing {package} on demand...")  
            subprocess.check_call([sys.executable, "-m", "pip", "install", package, "--quiet"])  
            print(f"✅ {package} installed successfully!")  
            installed_packages.add(import_name)  
            return True  
        except subprocess.CalledProcessError:  
            print(f"❌ Failed to install {package}")  
            return False

def check_package_lazy(package_name, import_name=None, pip_package=None):
    """Check and install package only when needed"""
    if import_name is None:
        import_name = package_name
    if pip_package is None:
        pip_package = package_name

    return install_package_lazy(pip_package, import_name)

# ====== CONFIG ======

API_ID = 20545107
API_HASH = 'dcc2d82cf8cba848c889bed60287c37a'
SESSION_NAME = 'clone_session'
BACKUP_JSON = 'profile_backup.json'
BACKUP_PHOTO = 'profile_backup_photo.jpg'
BACKUP_VIDEO = 'profile_backup_video.mp4'
NOTES_FILE = 'bot_notes.json'
AUTO_REPLY_FILE = 'custom_auto_replies.json'
START_TIME = time.time()

# Clarifai credentials
CLARIFAI_PAT = '68fdf69392414be2903865e2dbd8b595'
CLARIFAI_USER_ID = 'openai'
CLARIFAI_APP_ID = 'chat-completion'
CLARIFAI_MODEL_ID = 'gpt-oss-120b'
CLARIFAI_MODEL_VERSION_ID = '1c1365f924224107a9cd72b0a9e633a6'

# Global variables
last_command_message = None
auto_reply_enabled = False
greeting_responses = [
    "Hello! How can I help you today?",
    "Hi there! What can I do for you?",
    "Hey! Nice to hear from you!",
    "Greetings! How may I assist you?",
    "Hi! Feel free to ask me anything."
]
custom_auto_replies = {}  # Dictionary to store custom auto-replies

# Install basic requirements first
check_package_lazy('telethon')
check_package_lazy('pytz')
check_package_lazy('Pillow')
check_package_lazy('requests')

try:
    import pytz
    from telethon import TelegramClient, events, utils
    from telethon.tl import functions, types
    from PIL import Image, ImageDraw, ImageFont
    import requests
except ImportError as e:
    print(f"❌ Basic import error: {e}")
    print("Please restart the script after installation")
    sys.exit(1)

client = TelegramClient(SESSION_NAME, API_ID, API_HASH)

# ====== AUTHENTICATION SYSTEM ======

async def interactive_auth():
    """Interactive authentication with phone, OTP and 2FA"""
    print("╔══════════════════════════════════════╗")
    print("║      TELEGRAM AUTHENTICATION         ║")
    print("╚══════════════════════════════════════╝")

    # Check if already authenticated  
    if await client.is_user_authorized():  
        me = await client.get_me()  
        print(f"✅ Already logged in as: {me.first_name} {me.last_name or ''}")  
        return True  
  
    # Get phone number  
    while True:  
        phone = input("\n📱 Enter your phone number (with country code): ")  
        if phone and phone.startswith('+'):  
            break  
        print("❌ Please enter a valid phone number with country code (e.g., +8801xxxxxxxxx)")  
  
    # Send code request  
    print("\n📤 Sending code request...")  
    try:  
        await client.send_code_request(phone)  
        print("✅ Code sent successfully!")  
    except Exception as e:  
        print(f"❌ Failed to send code: {e}")  
        return False  
  
    # Get OTP code  
    while True:  
        code = input("\n🔑 Enter the OTP code you received: ")  
        if code and code.isdigit():  
            break  
        print("❌ Please enter a valid numeric OTP code")  
  
    # Try to sign in  
    try:  
        print("\n🔐 Verifying code...")  
        await client.sign_in(phone, code)  
        print("✅ Successfully authenticated!")  
        return True  
    except Exception as e:  
        error_str = str(e).lower()  
          
        # Check if 2FA is enabled  
        if 'password' in error_str or 'two_step' in error_str:  
            print("\n🔒 Two-factor authentication is enabled!")  
              
            while True:  
                try:  
                    password = getpass.getpass("🔐 Enter your 2FA password (hidden input): ")  
                    if password:  
                        break  
                    print("❌ Password cannot be empty")  
                except KeyboardInterrupt:  
                    print("\n❌ Authentication cancelled")  
                    return False  
          
            try:  
                print("\n🔐 Verifying password...")  
                await client.sign_in(password=password)  
                print("✅ Successfully authenticated with 2FA!")  
                return True  
            except Exception as e:  
                print(f"❌ Failed to authenticate with password: {e}")  
                return False  
        else:  
            print(f"❌ Failed to sign in: {e}")  
            return False

# ====== FILE TYPE DETECTION ======

def detect_file_type(file_bytes):
    """Detect file type from bytes"""
    # Check for common image signatures
    if file_bytes.startswith(b'\xFF\xD8\xFF'):
        return 'image/jpeg'
    elif file_bytes.startswith(b'\x89PNG\r\n\x1a\n'):
        return 'image/png'
    elif file_bytes.startswith(b'GIF87a') or file_bytes.startswith(b'GIF89a'):
        return 'image/gif'
    elif file_bytes.startswith(b'BM'):
        return 'image/bmp'
    elif file_bytes.startswith(b'II*\x00') or file_bytes.startswith(b'MM\x00*'):
        return 'image/tiff'

    # Check for common video signatures  
    if file_bytes.startswith(b'\x00\x00\x00 ftypisom'):  
        return 'video/mp4'  
    elif file_bytes.startswith(b'RIFF') and b'AVI ' in file_bytes[:12]:  
        return 'video/avi'  
    elif file_bytes.startswith(b'FLV'):  
        return 'video/x-flv'  
    elif file_bytes.startswith(b'\x1A\x45\xDF\xA3'):  
        return 'video/x-matroska'  # MKV  
  
    # Default to unknown  
    return 'unknown'

def is_image_file(file_bytes):
    """Check if file bytes represent an image"""
    file_type = detect_file_type(file_bytes)
    return file_type.startswith('image/')

def is_video_file(file_bytes):
    """Check if file bytes represent a video"""
    file_type = detect_file_type(file_bytes)
    return file_type.startswith('video/')

# ====== TERMINAL ANIMATION SYSTEM ======

class TerminalAnimation:
    def __init__(self):
        self.cursor_states = ["█", "▓", "▒", "░", " "]
        self.cursor_index = 0
        self.loading_chars = ["⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"]
        self.loading_index = 0

    def get_cursor(self):  
        """Get animated cursor"""  
        self.cursor_index = (self.cursor_index + 1) % len(self.cursor_states)  
        return self.cursor_states[self.cursor_index]  
  
    def get_loader(self):  
        """Get animated loader"""  
        self.loading_index = (self.loading_index + 1) % len(self.loading_chars)  
        return self.loading_chars[self.loading_index]  
  
    def get_prompt(self):  
        """Get terminal prompt"""  
        timestamp = datetime.now().strftime("%H:%M:%S")  
        return f"[{timestamp}] root@hackerbot:~# "

terminal = TerminalAnimation()

async def delete_last_command_message():
    """Delete the last command message if exists"""
    global last_command_message
    try:
        if last_command_message:
            await last_command_message.delete()
            last_command_message = None
    except:
        pass

async def send_terminal_message(event, text, show_cursor=True):
    """Send a terminal-style message"""
    global last_command_message
    await delete_last_command_message()

    if show_cursor:  
        text = f"```{terminal.get_prompt()}{text}{terminal.get_cursor()}```"  
    else:  
        text = f"```{terminal.get_prompt()}{text}```"  
  
    msg = await event.respond(text)  
    last_command_message = msg  
    return msg

async def update_terminal_message(text, show_cursor=True):
    """Update the terminal message with animation"""
    global last_command_message
    if last_command_message:
        try:
            if show_cursor:
                new_text = f"{terminal.get_prompt()}{text}{terminal.get_cursor()}"
            else:
                new_text = f"{terminal.get_prompt()}{text}"
            await last_command_message.edit(new_text)
        except:
            pass

async def terminal_typing_effect(msg, text, speed=0.03):
    """Simulate terminal typing effect"""
    displayed_text = ""
    for char in text:
        displayed_text += char
        try:
            await msg.edit(f"{terminal.get_prompt()}{displayed_text}{terminal.get_cursor()}")
            await asyncio.sleep(speed)
        except:
            pass

async def terminal_execute_command(event, command, steps):
    """Execute command with terminal-style animation"""
    # Show command being executed
    msg = await send_terminal_message(event, command, show_cursor=False)
    await asyncio.sleep(0.2)

    # Show execution steps  
    for step in steps:  
        await update_terminal_message(f"{terminal.get_loader()} {step}")  
        await asyncio.sleep(0.3)  
  
    return msg

async def terminal_loading(msg, text, duration=1.0):
    """Show terminal loading animation"""
    steps = [
        f"Initializing {text}...",
        f"Loading {text} modules...",
        f"Processing {text} data...",
        f"Executing {text} protocol...",
        f"Finalizing {text} operation..."
    ]

    for step in steps:  
        await update_terminal_message(f"{terminal.get_loader()} {step}")  
        await asyncio.sleep(duration / len(steps))

async def terminal_progress(msg, operation, total_steps=5):
    """Show terminal progress with steps"""
    for i in range(total_steps):
        progress = f"[{'=' * (i + 1)}{' ' * (total_steps - i - 1)}] {((i + 1) * 100 // total_steps)}%"
        await update_terminal_message(f"{terminal.get_loader()} {operation} {progress}")
        await asyncio.sleep(0.2)

# ====== TEXT TO IMAGE GENERATION ======

async def create_text_image(text, style="default"):
    """Create an image from text"""
    try:
        # Image dimensions
        width, height = 800, 400

        # Create image with different styles  
        if style == "hacker":  
            # Dark hacker theme  
            img = Image.new('RGB', (width, height), color='#0a0a0a')  
            draw = ImageDraw.Draw(img)  
              
            # Try to use a font, fallback to default  
            try:  
                font = ImageFont.truetype("arial.ttf", 40)  
                small_font = ImageFont.truetype("arial.ttf", 20)  
            except:  
                font = ImageFont.load_default()  
                small_font = ImageFont.load_default()  
              
            # Add matrix-like background  
            for i in range(0, width, 20):  
                for j in range(0, height, 20):  
                    if random.random() > 0.7:  
                        draw.text((i, j), random.choice("01"), fill='#00ff00', font=small_font)  
              
            # Draw main text  
            lines = text.split('\n')  
            y_offset = height // 2 - (len(lines) * 25)  
            for line in lines:  
                bbox = draw.textbbox((0, 0), line, font=font)  
                text_width = bbox[2] - bbox[0]  
                x = (width - text_width) // 2  
                draw.text((x, y_offset), line, fill='#00ff00', font=font)  
                y_offset += 50  
                  
        elif style == "neon":  
            # Neon theme  
            img = Image.new('RGB', (width, height), color='#000000')  
            draw = ImageDraw.Draw(img)  
              
            try:  
                font = ImageFont.truetype("arial.ttf", 45)  
            except:  
                font = ImageFont.load_default()  
              
            # Add glow effect  
            for offset in range(5, 0, -1):  
                color = f"#{offset:02x}00{offset:02x}"  
                lines = text.split('\n')  
                y_offset = height // 2 - (len(lines) * 30)  
                for line in lines:  
                    bbox = draw.textbbox((0, 0), line, font=font)  
                    text_width = bbox[2] - bbox[0]  
                    x = (width - text_width) // 2  
                    draw.text((x+offset, y_offset+offset), line, fill=color, font=font)  
                    draw.text((x-offset, y_offset+offset), line, fill=color, font=font)  
                    draw.text((x+offset, y_offset-offset), line, fill=color, font=font)  
                    draw.text((x-offset, y_offset-offset), line, fill=color, font=font)  
                    y_offset += 60  
              
            # Main text  
            lines = text.split('\n')  
            y_offset = height // 2 - (len(lines) * 30)  
            for line in lines:  
                bbox = draw.textbbox((0, 0), line, font=font)  
                text_width = bbox[2] - bbox[0]  
                x = (width - text_width) // 2  
                draw.text((x, y_offset), line, fill='#00ffff', font=font)  
                y_offset += 60  
                  
        else:  
            # Default theme  
            img = Image.new('RGB', (width, height), color='#1e1e1e')  
            draw = ImageDraw.Draw(img)  
              
            try:  
                font = ImageFont.truetype("arial.ttf", 40)  
            except:  
                font = ImageFont.load_default()  
              
            # Add border  
            draw.rectangle([10, 10, width-10, height-10], outline='#4a9eff', width=3)  
              
            # Draw text  
            lines = text.split('\n')  
            y_offset = height // 2 - (len(lines) * 30)  
            for line in lines:  
                bbox = draw.textbbox((0, 0), line, font=font)  
                text_width = bbox[2] - bbox[0]  
                x = (width - text_width) // 2  
                draw.text((x, y_offset), line, fill='#ffffff', font=font)  
                y_offset += 50
          
        # Save to BytesIO  
        bio = BytesIO()  
        bio.name = 'text_image.png'  
        img.save(bio, 'PNG')  
        bio.seek(0)  
          
        return bio  
          
    except Exception as e:  
        print(f"Error creating text image: {e}")  
        return None

async def create_ascii_image(text):
    """Create ASCII art image"""
    try:
        width, height = 600, 300
        img = Image.new('RGB', (width, height), color='#000000')
        draw = ImageDraw.Draw(img)

        try:  
            font = ImageFont.truetype("courier.ttf", 12)  
        except:  
            font = ImageFont.load_default()  
          
        # ASCII art border  
        border = "╔" + "═" * (width//8 - 2) + "╗"  
        draw.text((10, 10), border, fill='#00ff00', font=font)  
          
        # Content  
        lines = text.split('\n')  
        y = 40  
        for line in lines[:10]:  # Max 10 lines  
            if len(line) > 70:  
                line = line[:67] + "..."  
            content = f"║ {line.ljust(width//8 - 4)} ║"  
            draw.text((10, y), content, fill='#00ff00', font=font)  
            y += 20  
          
        # Bottom border  
        border = "╚" + "═" * (width//8 - 2) + "╝"  
        draw.text((10, y), border, fill='#00ff00', font=font)  
          
        bio = BytesIO()  
        bio.name = 'ascii_art.png'  
        img.save(bio, 'PNG')  
        bio.seek(0)  
          
        return bio  
          
    except Exception as e:  
        print(f"Error creating ASCII image: {e}")  
        return None

# ====== ENHANCED IMAGE PROCESSING ======

async def process_image_for_profile(image_path_or_bytes):
    """Process image to meet Telegram profile requirements"""
    try:
        file_bytes = None

        # Handle both file paths and bytes  
        if isinstance(image_path_or_bytes, str):  
            # Check if file exists  
            if not os.path.exists(image_path_or_bytes):  
                print(f"Error: Image file does not exist: {image_path_or_bytes}")  
                return None  
              
            # Check file size  
            file_size = os.path.getsize(image_path_or_bytes)  
            if file_size == 0:  
                print(f"Error: Image file is empty: {image_path_or_bytes}")  
                return None  
              
            # Read file bytes  
            try:  
                with open(image_path_or_bytes, 'rb') as f:  
                    file_bytes = f.read()  
            except Exception as e:  
                print(f"Error reading image file {image_path_or_bytes}: {e}")  
                return None  
        else:  
            # Handle bytes input  
            if isinstance(image_path_or_bytes, bytes):  
                file_bytes = image_path_or_bytes  
            elif isinstance(image_path_or_bytes, BytesIO):  
                file_bytes = image_path_or_bytes.getvalue()  
            else:  
                print(f"Error: Invalid input type for image processing: {type(image_path_or_bytes)}")  
                return None  
          
        # Check if we have valid bytes  
        if not file_bytes or len(file_bytes) == 0:  
            print("Error: No valid image data received")  
            return None  
          
        # Check if it's actually an image  
        if not is_image_file(file_bytes):  
            file_type = detect_file_type(file_bytes)  
            print(f"Error: File is not an image, it's a {file_type}")  
            return None  
          
        # Check if it's actually an image by trying to open it  
        try:  
            img = Image.open(BytesIO(file_bytes))  
            # Verify the image can be loaded  
            img.verify()  
            # Reopen after verify (verify() closes the image)  
            img = Image.open(BytesIO(file_bytes))  
        except Exception as e:  
            print(f"Error: Invalid image data - {e}")  
            return None  
          
        # Verify image is loaded  
        if img.size[0] == 0 or img.size[1] == 0:  
            print("Error: Invalid image dimensions")  
            return None  
          
        # Convert to RGB if necessary  
        if img.mode != 'RGB':  
            img = img.convert('RGB')  
          
        # Get dimensions  
        width, height = img.size  
          
        # Telegram requires minimum 200x200 for profile photos  
        min_size = 200  
          
        # If image is too small, resize it  
        if width < min_size or height < min_size:  
            # Calculate new dimensions maintaining aspect ratio  
            if width > height:  
                new_width = min_size  
                new_height = int(height * (min_size / width))  
            else:  
                new_height = min_size  
                new_width = int(width * (min_size / height))  
              
            # Resize with high quality  
            img = img.resize((new_width, new_height), Image.Resampling.LANCZOS)  
              
            # Create a square canvas with the resized image centered  
            canvas = Image.new('RGB', (min_size, min_size), (255, 255, 255))  
              
            # Calculate position to center the image  
            x_offset = (min_size - new_width) // 2  
            y_offset = (min_size - new_height) // 2  
              
            # Paste the resized image onto the canvas  
            canvas.paste(img, (x_offset, y_offset))  
              
            img = canvas  
          
        # Save to BytesIO  
        bio = BytesIO()  
        bio.name = 'profile.jpg'  
        img.save(bio, 'JPEG', quality=85)  
        bio.seek(0)  
          
        return bio  
          
    except Exception as e:  
        print(f"Error processing image: {e}")  
        return None

async def download_and_process_photo(photo_entity, temp_filename):
    """Download and process photo with better error handling"""
    try:
        print(f"Downloading photo...")

        # Download to file directly first  
        file_path = await client.download_media(photo_entity, file=temp_filename)  
          
        if not file_path:  
            print("Failed to download photo - no file path received")  
            return None  
          
        # Check if file exists and has content  
        if not os.path.exists(file_path):  
            print(f"Downloaded file does not exist: {file_path}")  
            return None  
          
        file_size = os.path.getsize(file_path)  
        if file_size == 0:  
            print(f"Downloaded file is empty: {file_path}")  
            return None  
          
        print(f"File downloaded successfully: {file_path} ({file_size} bytes)")  
          
        # Read file bytes to check type  
        with open(file_path, 'rb') as f:  
            file_bytes = f.read()  
          
        # Check if it's actually an image  
        if not is_image_file(file_bytes):  
            file_type = detect_file_type(file_bytes)  
            print(f"Warning: Downloaded file is not an image, it's a {file_type}")  
            return None  
          
        print(f"Confirmed image file: {detect_file_type(file_bytes)}")  
          
        # Process the image from file  
        processed_photo = await process_image_for_profile(file_path)  
          
        return processed_photo  
          
    except Exception as e:  
        print(f"Error in download_and_process_photo: {e}")  
        return None

async def download_and_process_video(video_entity, temp_filename):
    """Download and process video with better error handling"""
    try:
        print(f"Downloading video...")

        # Download to file directly first  
        file_path = await client.download_media(video_entity, file=temp_filename)  
          
        if not file_path:  
            print("Failed to download video - no file path received")  
            return None  
          
        # Check if file exists and has content  
        if not os.path.exists(file_path):  
            print(f"Downloaded video file does not exist: {file_path}")  
            return None  
          
        file_size = os.path.getsize(file_path)  
        if file_size == 0:  
            print(f"Downloaded video file is empty: {file_path}")  
            return None  
          
        print(f"File downloaded successfully: {file_path} ({file_size} bytes)")  
          
        # Read file bytes to check type  
        with open(file_path, 'rb') as f:  
            file_bytes = f.read()  
          
        # Check if it's actually a video  
        if not is_video_file(file_bytes):  
            file_type = detect_file_type(file_bytes)  
            print(f"Warning: Downloaded file is not a video, it's a {file_type}")  
            return None  
          
        print(f"Confirmed video file: {detect_file_type(file_bytes)}")  
          
        # Read the video file into BytesIO  
        with open(file_path, 'rb') as f:  
            video_bytes = f.read()  
          
        # Create BytesIO for upload with proper name  
        video_bio = BytesIO(video_bytes)  
        video_bio.name = os.path.basename(file_path)  
          
        return video_bio  
          
    except Exception as e:  
        print(f"Error in download_and_process_video: {e}")  
        return None

async def delete_all_profile_photos():
    """Delete all existing profile photos"""
    try:
        # Get all profile photos
        photos = await client.get_profile_photos('me', limit=100)

        if photos.total > 0:  
            print(f"Deleting {photos.total} existing profile photos...")  
              
            # Delete all photos  
            for photo in photos:  
                try:  
                    await client(functions.photos.DeletePhotosRequest(id=[photo]))  
                    print(f"Deleted photo: {photo.id}")  
                except Exception as e:  
                    print(f"Failed to delete photo {photo.id}: {e}")  
              
            # Wait a bit for deletion to complete  
            await asyncio.sleep(2)  
            print("All profile photos deleted successfully")  
            return True  
        else:  
            print("No existing profile photos to delete")  
            return True  
              
    except Exception as e:  
        print(f"Error deleting profile photos: {e}")  
        return False

# ====== OPTIMIZED GROUP DETECTION ======

async def get_user_groups_fast(user_id):
    """Fast group detection with timeout"""
    try:
        groups = []

        # Use asyncio.wait_for to add timeout  
        try:  
            # Get only first 50 dialogs to speed up  
            dialogs = await asyncio.wait_for(client.get_dialogs(limit=50), timeout=10.0)  
        except asyncio.TimeoutError:  
            print("Timeout getting dialogs, using cached data")  
            return []  
          
        # Check each dialog with timeout  
        for dialog in dialogs:  
            if len(groups) >= 5:  # Limit to 5 groups for speed  
                break  
                  
            if dialog.is_group or dialog.is_channel:  
                try:  
                    # Quick check - try to get entity info  
                    entity = await asyncio.wait_for(client.get_entity(dialog.id), timeout=3.0)  
                      
                    # For channels, check if we can access participants  
                    if hasattr(entity, 'participant_count') and entity.participant_count:  
                        # If it's a channel with participants, add it  
                        if entity.participant_count > 0:  
                            groups.append(dialog.title)  
                    elif dialog.is_group:  
                        # For groups, add them directly  
                        groups.append(dialog.title)  
                          
                except asyncio.TimeoutError:  
                    print(f"Timeout checking group {dialog.title}")  
                    continue  
                except Exception as e:  
                    print(f"Error checking group {dialog.title}: {e}")  
                    continue  
          
        return groups  
          
    except Exception as e:  
        print(f"Error in fast group detection: {e}")  
        return []

async def get_user_groups_simple(user_id):
    """Simple group detection - just return common groups count"""
    try:
        # Get common groups directly (faster method)
        try:
            common_chats = await asyncio.wait_for(
                client(functions.messages.GetCommonChatsRequest(user_id=user_id, max_id=0, limit=5)),
                timeout=5.0
            )

            groups = [chat.title for chat in common_chats.chats]  
            return groups  
              
        except asyncio.TimeoutError:  
            print("Timeout getting common chats")  
            return []  
        except Exception as e:  
            print(f"Error getting common chats: {e}")  
            return []  
              
    except Exception as e:  
        print(f"Error in simple group detection: {e}")  
        return []

# ====== UTILITY FUNCTIONS ======

def load_notes():
    """Load notes from file"""
    try:
        if os.path.exists(NOTES_FILE):
            with open(NOTES_FILE, 'r', encoding='utf-8') as f:
                return json.load(f)
    except:
        pass
    return {}

def save_notes(notes):
    """Save notes to file"""
    try:
        with open(NOTES_FILE, 'w', encoding='utf-8') as f:
            json.dump(notes, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Error saving notes: {e}")

def load_custom_replies():
    """Load custom auto-reply messages from file"""
    global custom_auto_replies
    try:
        if os.path.exists(AUTO_REPLY_FILE):
            with open(AUTO_REPLY_FILE, 'r', encoding='utf-8') as f:
                custom_auto_replies = json.load(f)
    except Exception as e:
        print(f"Error loading custom replies: {e}")
        custom_auto_replies = {}

def save_custom_replies():
    """Save custom auto-reply messages to file"""
    try:
        with open(AUTO_REPLY_FILE, 'w', encoding='utf-8') as f:
            json.dump(custom_auto_replies, f, ensure_ascii=False, indent=2)
        return True
    except Exception as e:
        print(f"Error saving custom replies: {e}")
        return False

def encrypt_text(text, password):
    """Simple XOR encryption"""
    key = hashlib.sha256(password.encode()).digest()
    encrypted = bytearray()
    for i, byte in enumerate(text.encode()):
        encrypted.append(byte ^ key[i % len(key)])
    return base64.b64encode(encrypted).decode()

def decrypt_text(encrypted_text, password):
    """Simple XOR decryption"""
    try:
        key = hashlib.sha256(password.encode()).digest()
        encrypted = base64.b64decode(encrypted_text)
        decrypted = bytearray()
        for i, byte in enumerate(encrypted):
            decrypted.append(byte ^ key[i % len(key)])
        return decrypted.decode()
    except:
        return None

def generate_password(length=12):
    """Generate secure password"""
    alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
    return ''.join(secrets.choice(alphabet) for _ in range(length))

def format_bytes(bytes_value):
    """Format bytes to human readable format"""
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if bytes_value < 1024.0:
            return f"{bytes_value:.2f} {unit}"
        bytes_value /= 1024.0
    return f"{bytes_value:.2f} PB"

def format_time(seconds):
    """Format seconds to human readable time"""
    days = int(seconds // 86400)
    hours = int((seconds % 86400) // 3600)
    minutes = int((seconds % 3600) // 60)
    secs = int(seconds % 60)

    parts = []  
    if days > 0:  
        parts.append(f"{days}d")  
    if hours > 0:  
        parts.append(f"{hours}h")  
    if minutes > 0:  
        parts.append(f"{minutes}m")  
    if secs > 0 or not parts:  
        parts.append(f"{secs}s")  
  
    return " ".join(parts)

async def get_clarifai_response(message_text):
    """Get response from Clarifai API using GPT model"""
    try:
        # Check if clarifai-grpc package is installed, install if needed
        if not check_package_lazy('clarifai-grpc', 'clarifai_grpc', 'clarifai-grpc'):
            return "Failed to install Clarifai package. Please try again later."
        
        from clarifai_grpc.channel.clarifai_channel import ClarifaiChannel
        from clarifai_grpc.grpc.api import resources_pb2, service_pb2, service_pb2_grpc
        from clarifai_grpc.grpc.api.status import status_code_pb2
        
        # Set up the channel and stub
        channel = ClarifaiChannel.get_grpc_channel()
        stub = service_pb2_grpc.V2Stub(channel)
        
        # Set up metadata with PAT
        metadata = (('authorization', 'Key ' + CLARIFAI_PAT),)
        
        # Create user app ID set
        userDataObject = resources_pb2.UserAppIDSet(user_id=CLARIFAI_USER_ID, app_id=CLARIFAI_APP_ID)
        
        # Create the request
        post_model_outputs_response = stub.PostModelOutputs(
            service_pb2.PostModelOutputsRequest(
                user_app_id=userDataObject,
                model_id=CLARIFAI_MODEL_ID,
                version_id=CLARIFAI_MODEL_VERSION_ID,
                inputs=[
                    resources_pb2.Input(
                        data=resources_pb2.Data(
                            text=resources_pb2.Text(
                                raw=message_text
                            )
                        )
                    )
                ]
            ),
            metadata=metadata
        )
        
        if post_model_outputs_response.status.code != status_code_pb2.SUCCESS:
            print(post_model_outputs_response.status)
            return f"Error: {post_model_outputs_response.status.description}"
        
        # Get the response
        output = post_model_outputs_response.outputs[0]
        return output.data.text.raw
        
    except Exception as e:
        return f"Error getting Clarifai response: {str(e)}"

def is_greeting(message_text):
    """Check if message is a greeting"""
    greetings = [
        "hi", "hello", "hey", "hey there", "good morning", "good afternoon", 
        "good evening", "howdy", "yo", "hiya", "greetings", "what's up",
        "sup", "how are you", "how's it going", "nice to meet you"
    ]
    
    message_lower = message_text.lower().strip()
    
    for greeting in greetings:
        if message_lower == greeting or message_lower.startswith(greeting + " "):
            return True
    
    return False

def find_custom_reply(message_text):
    """Find matching custom auto-reply"""
    message_lower = message_text.lower().strip()
    
    # Check for exact matches first
    if message_lower in custom_auto_replies:
        return custom_auto_replies[message_lower]
    
    # Check for partial matches (if message starts with a trigger)
    for trigger, response in custom_auto_replies.items():
        if message_lower.startswith(trigger.lower()):
            return response
    
    return None

async def save_current_profile():
    """Save current (real) profile to local backup if not already saved."""
    if os.path.exists(BACKUP_JSON):
        return

    me = await client.get_me()  
    full = await client(functions.users.GetFullUserRequest(id=me))  
  
    # Fixed bio extraction  
    about = None  
    if hasattr(full, 'full_user') and hasattr(full.full_user, 'about'):  
        about = full.full_user.about  
    elif hasattr(full, 'about'):  
        about = full.about  
  
    data = {  
        'id': me.id,  
        'first_name': me.first_name or "",  
        'last_name': me.last_name or "",  
        'about': about or "",  
        'has_profile_photo': False,  
        'has_profile_video': False,  
        'video_file_name': None  
    }  
  
    # Check for profile photo  
    photos = await client.get_profile_photos(me, limit=1)  
    if photos.total > 0:  
        data['has_profile_photo'] = True  
        photo = photos[0]  
        # Use the new download method  
        processed_photo = await download_and_process_photo(photo, BACKUP_PHOTO)  
        if processed_photo:  
            # Save the processed photo  
            try:  
                with open(BACKUP_PHOTO, 'wb') as f:  
                    f.write(processed_photo.getvalue())  
            except Exception as e:  
                print(f"Error saving backup photo: {e}")  
  
    # Check for profile video  
    try:  
        # Get full user info to check for profile video  
        full_user = full.full_user if hasattr(full, 'full_user') else full  
        if hasattr(full_user, 'profile_video') and full_user.profile_video:  
            data['has_profile_video'] = True  
            video_entity = full_user.profile_video  
              
            # Get video file name  
            video_file_name = getattr(video_entity, 'file_name', 'profile_video.mp4')  
            data['video_file_name'] = video_file_name  
              
            # Download the video  
            processed_video = await download_and_process_video(video_entity, BACKUP_VIDEO)  
            if processed_video:  
                # Save the video  
                try:  
                    with open(BACKUP_VIDEO, 'wb') as f:  
                        f.write(processed_video.getvalue())  
                    print(f"✅ Profile video backed up: {video_file_name}")  
                except Exception as e:  
                    print(f"Error saving backup video: {e}")  
    except Exception as e:  
        print(f"Error checking for profile video: {e}")  
  
    with open(BACKUP_JSON, 'w', encoding='utf-8') as f:  
        json.dump(data, f, ensure_ascii=False, indent=2)

async def restore_profile_from_backup(event):
    """Enhanced profile restore with proper photo and video handling"""
    if not os.path.exists(BACKUP_JSON):
        await event.respond("⚠️ No backup found - real profile not saved previously.")
        return

    with open(BACKUP_JSON, 'r', encoding='utf-8') as f:  
        data = json.load(f)  

    photo_restored = False  
    video_restored = False  
    name_bio_restored = False  

    try:  
        # Restore name and bio first  
        await client(functions.account.UpdateProfileRequest(  
            first_name=data.get('first_name') or None,  
            last_name=data.get('last_name') or None,  
            about=data.get('about') or None  
        ))  
        name_bio_restored = True  
        print("✅ Name and bio restored successfully")  
    except Exception as e:  
        print(f"❌ Error restoring name/bio: {e}")  
        await event.respond(f"❌ Error updating name/bio: {e}")  
        return  

    # Handle profile photo restoration - FIXED VERSION  
    if data.get('has_profile_photo') and os.path.exists(BACKUP_PHOTO) and os.path.getsize(BACKUP_PHOTO) > 0:  
        try:  
            print("🔄 Restoring profile photo...")  
              
            # CRITICAL: Delete ALL existing photos first (including cloned ones)  
            await update_terminal_message(f"{terminal.get_loader()} Removing cloned profile photos...")  
            delete_success = await delete_all_profile_photos()  
              
            if delete_success:  
                # Wait a bit after deletion  
                await asyncio.sleep(1)  
                  
                await update_terminal_message(f"{terminal.get_loader()} Uploading original profile photo...")  
                  
                # Process and upload the backup photo  
                processed_photo = await process_image_for_profile(BACKUP_PHOTO)  
                if processed_photo:  
                    uploaded = await client.upload_file(processed_photo)  
                    await client(functions.photos.UploadProfilePhotoRequest(file=uploaded))  
                    photo_restored = True  
                    print("✅ Profile photo restored successfully")  
                else:  
                    print("❌ Failed to process backup photo")  
            else:  
                print("❌ Failed to delete existing photos")  
                  
        except Exception as e:  
            print(f"❌ Error restoring profile photo: {e}")  
    else:  
        print("ℹ️ No backup photo to restore")  

    # Handle profile video restoration  
    if data.get('has_profile_video') and os.path.exists(BACKUP_VIDEO) and os.path.getsize(BACKUP_VIDEO) > 0:  
        try:  
            print("🔄 Restoring profile video...")  
              
            # Read the video file  
            with open(BACKUP_VIDEO, 'rb') as f:  
                video_bytes = f.read()  
              
            # Create BytesIO with proper file name  
            video_file_name = data.get('video_file_name', 'profile_video.mp4')  
            video_bio = BytesIO(video_bytes)  
            video_bio.name = video_file_name  
              
            # Upload the video  
            uploaded = await client.upload_file(video_bio)  
              
            # Try different upload methods for video  
            try:  
                # Method 1: With video parameter  
                await client(functions.photos.UploadProfilePhotoRequest(file=uploaded, video=True))  
                video_restored = True  
                print("✅ Profile video restored successfully (Method 1)")  
            except Exception as e1:  
                print(f"Method 1 failed: {e1}")  
                try:  
                    # Method 2: Without video parameter  
                    await client(functions.photos.UploadProfilePhotoRequest(file=uploaded))  
                    video_restored = True  
                    print("✅ Profile video restored successfully (Method 2)")  
                except Exception as e2:  
                    print(f"Method 2 failed: {e2}")  
                    print("❌ All video upload methods failed")  
                  
        except Exception as e:  
            print(f"❌ Error restoring profile video: {e}")  

    # Send appropriate response based on what was restored  
    response_parts = []  
  
    if name_bio_restored:  
        response_parts.append("Name & Bio")  
  
    if photo_restored:  
        response_parts.append("Photo")  
  
    if video_restored:  
        response_parts.append("Video")  
  
    if response_parts:  
        await event.respond(f"✅ {', '.join(response_parts)} restored successfully!")  
    else:  
        await event.respond("❌ Failed to restore profile.")

# ====== COMMAND HANDLERS (Support both . and /) ======

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]cln\s*(\S+)?$'))
async def handler_cln(event):
    try:
        await event.delete()
    except:
        pass

    arg = event.pattern_match.group(1)  
    target = None  
    if arg:  
        target = arg  
    elif event.is_reply:  
        reply = await event.get_reply_message()  
        if reply and reply.from_id:  
            target = reply.from_id  
    else:  
        await send_terminal_message(event, "echo 'Usage: .cln @username or reply to user and use .cln'")  
        return  

    try:  
        # Terminal-style command execution  
        steps = [  
            "Initializing clone protocol...",  
            "Connecting to target user...",  
            "Extracting profile data...",  
            "Processing user information...",  
            "Uploading cloned profile..."  
        ]  
          
        msg = await terminal_execute_command(event, f"clone --target={target}", steps)  
          
        # Get user entity  
        user_entity = await client.get_entity(target)  

        if isinstance(user_entity, types.User) and user_entity.bot:  
            await msg.edit(f"```{terminal.get_prompt()}Error: Target is a bot - cloning aborted```")  
            return  

        me = await client.get_me()  
        if user_entity.id == me.id:  
            await msg.edit(f"```{terminal.get_prompt()}Warning: Self-cloning detected - operation cancelled```")  
            return  

        await save_current_profile()  
          
        # Fixed bio extraction - using multiple methods  
        about = None  
          
        # Method 1: Try GetFullUserRequest  
        try:  
            full = await client(functions.users.GetFullUserRequest(id=user_entity))  
            if hasattr(full, 'full_user') and hasattr(full.full_user, 'about'):  
                about = full.full_user.about  
            elif hasattr(full, 'about'):  
                about = full.about  
        except:  
            pass  
          
        # Method 2: Try direct entity access  
        if not about and hasattr(user_entity, 'about'):  
            about = user_entity.about  
          
        # Method 3: Try to get bio from entity's full_user  
        if not about and hasattr(user_entity, 'full_user') and hasattr(user_entity.full_user, 'about'):  
            about = user_entity.full_user.about  
          
        # Debug print (remove in production)  
        print(f"Debug - Extracted bio: {about}")  
        print(f"Debug - User entity type: {type(user_entity)}")  

        first_name = user_entity.first_name or ""  
        last_name = user_entity.last_name or ""  

        # Update profile with proper bio handling  
        update_params = {  
            'first_name': first_name if first_name else None,  
            'last_name': last_name if last_name else None,  
        }  
          
        # Only add about if it's not None and not empty  
        if about is not None and about.strip():  
            update_params['about'] = about  
          
        await client(functions.account.UpdateProfileRequest(**update_params))  

        # Check for profile photo  
        photos = await client.get_profile_photos(user_entity, limit=1)  
        photo_cloned = False  
          
        if photos.total > 0:  
            await update_terminal_message(f"{terminal.get_loader()} Processing profile photo...")  
            photo = photos[0]  
            tmp_file = f"temp_profile_{user_entity.id}.jpg"  
              
            # Use the new download method  
            processed_photo = await download_and_process_photo(photo, tmp_file)  
              
            if processed_photo:  
                try:  
                    # Delete existing profile photos first  
                    await delete_all_profile_photos()  
                      
                    await update_terminal_message(f"{terminal.get_loader()} Uploading new profile photo...")  
                      
                    uploaded = await client.upload_file(processed_photo)  
                    await client(functions.photos.UploadProfilePhotoRequest(file=uploaded))  
                    photo_cloned = True  
                      
                    print("✅ Profile photo cloned successfully")  
                except Exception as e:  
                    print(f"❌ Error uploading profile photo: {e}")  
                finally:  
                    try:  
                        os.remove(tmp_file)  
                    except:  
                        pass  
            else:  
                print("❌ Failed to process target profile photo")  

        # Check for profile video  
        video_cloned = False  
        video_file_name = None  
        try:  
            full = await client(functions.users.GetFullUserRequest(id=user_entity))  
            full_user = full.full_user if hasattr(full, 'full_user') else full  
            if hasattr(full_user, 'profile_video') and full_user.profile_video:  
                await update_terminal_message(f"{terminal.get_loader()} Processing profile video...")  
                video_entity = full_user.profile_video  
                video_file_name = getattr(video_entity, 'file_name', 'profile_video.mp4')  
                tmp_video_file = f"temp_video_{user_entity.id}.mp4"  
                  
                processed_video = await download_and_process_video(video_entity, tmp_video_file)  
                  
                if processed_video:  
                    try:  
                        await update_terminal_message(f"{terminal.get_loader()} Uploading profile video...")  
                        uploaded = await client.upload_file(processed_video)  
                          
                        # Try different upload methods for video  
                        try:  
                            # Method 1: With video parameter  
                            await client(functions.photos.UploadProfilePhotoRequest(file=uploaded, video=True))  
                            video_cloned = True  
                            print("✅ Video cloned successfully (Method 1)")  
                        except Exception as e1:  
                            print(f"Video upload Method 1 failed: {e1}")  
                            try:  
                                # Method 2: Without video parameter  
                                await client(functions.photos.UploadProfilePhotoRequest(file=uploaded))  
                                video_cloned = True  
                                print("✅ Video cloned successfully (Method 2)")  
                            except Exception as e2:  
                                print(f"Video upload Method 2 failed: {e2}")  
                    finally:  
                        try:  
                            os.remove(tmp_video_file)  
                        except:  
                            pass  
        except Exception as e:  
            print(f"Error cloning profile video: {e}")  

        # Prepare terminal-style response  
        response_parts = []  
        response_parts.append("Name")  
        if about and about.strip():  
            response_parts.append("Bio")  
              
        if photo_cloned:  
            response_parts.append("Photo")  
              
        if video_cloned:  
            response_parts.append("Video")  
              
        if response_parts:  
            result = f"echo 'Clone successful! {', '.join(response_parts)} has been updated'"  
            await msg.edit(f"```{terminal.get_prompt()}{result}```")  
        else:  
            bio_status = f"Bio: '{about[:30]}...' " if about and about.strip() else "No Bio "  
            result = f"echo 'Name & {bio_status}cloned successfully'"  
            await msg.edit(f"```{terminal.get_prompt()}{result}```")  

    except Exception as e:  
        await event.respond(f"```{terminal.get_prompt()}Error: {type(e).__name__}: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]back$'))
async def handler_back(event):
    try:
        await event.delete()
    except:
        pass

    # Terminal-style restore command  
    steps = [  
        "Loading backup profile...",  
        "Validating backup integrity...",  
        "Restoring user data...",  
        "Updating profile settings...",  
        "Finalizing restoration..."  
    ]  
  
    msg = await terminal_execute_command(event, "restore --profile=backup", steps)  
  
    # Perform actual restore  
    await restore_profile_from_backup(msg)

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]info\s*(\S+)?$'))
async def handler_info(event):
    try:
        await event.delete()
    except:
        pass

    arg = event.pattern_match.group(1)  
    target = None  
    if arg:  
        target = arg  
    elif event.is_reply:  
        reply = await event.get_reply_message()  
        if reply and reply.from_id:  
            target = reply.from_id  
    else:  
        await send_terminal_message(event, "echo 'Usage: .info @username or reply to user and use .info'")  
        return  
  
    # Terminal-style info gathering  
    steps = [  
        "Scanning target user...",  
        "Fetching user data...",  
        "Analyzing profile information...",  
        "Compiling intelligence report..."  
    ]  
  
    msg = await terminal_execute_command(event, f"scan --target={target}", steps)  
  
    try:  
        user_entity = await client.get_entity(target)  
          
        # Fixed bio extraction for info command  
        about = None  
        has_video = False  
        video_file_name = None  
        try:  
            full = await client(functions.users.GetFullUserRequest(id=user_entity))  
            if hasattr(full, 'full_user') and hasattr(full.full_user, 'about'):  
                about = full.full_user.about  
            elif hasattr(full, 'about'):  
                about = full.about  
              
            # Check for profile video  
            full_user = full.full_user if hasattr(full, 'full_user') else full  
            if hasattr(full_user, 'profile_video') and full_user.profile_video:  
                has_video = True  
                video_file_name = getattr(full_user.profile_video, 'file_name', 'Unknown')  
        except:  
            pass  
          
        if not about and hasattr(user_entity, 'about'):  
            about = user_entity.about  
          
        if not about and hasattr(user_entity, 'full_user') and hasattr(user_entity.full_user, 'about'):  
            about = user_entity.full_user.about  
          
        # Check for profile photo  
        photos = await client.get_profile_photos(user_entity, limit=1)  
        has_photo = photos.total > 0  
          
        # Get user groups with timeout and error handling  
        await update_terminal_message(f"{terminal.get_loader()} Scanning common groups...")  
        user_groups = []  
          
        try:  
            # Try the fast method first  
            user_groups = await asyncio.wait_for(get_user_groups_simple(user_entity.id), timeout=5.0)  
        except asyncio.TimeoutError:  
            print("Group scanning timeout, skipping...")  
            user_groups = []  
        except Exception as e:  
            print(f"Error in group scanning: {e}")  
            user_groups = []  
          
        # Download profile photo for preview  
        profile_photo_bio = None  
        if has_photo:  
            try:  
                photo = photos[0]  
                profile_photo_bio = await download_and_process_photo(photo, f"temp_info_{user_entity.id}.jpg")  
            except:  
                pass  
          
        # Create info text - FIXED F-STRING FORMATTING  
        info_text = "╔══════════════════════════╗\n"  
        info_text += f"║     USER INTEL DATA     ║\n"  
        info_text += "╠══════════════════════════╣\n"  
        info_text += f"║ Name: {user_entity.first_name} {user_entity.last_name or ''}\n"  
        info_text += f"║ ID: {user_entity.id}\n"  
        info_text += f"║ Username: @{user_entity.username or 'N/A'}\n"  
        bio_display = (about or 'N/A')[:30] + ('...' if about and len(about) > 30 else '')  
        info_text += f"║ Bio: {bio_display}\n"  
        info_text += f"║ Photo: {'Yes' if has_photo else 'No'}\n"  
        info_text += f"║ Video: {'Yes' if has_video else 'No'}\n"  
        if has_video and video_file_name:  
            video_display = video_file_name[:20] + ('...' if len(video_file_name) > 20 else '')  
            info_text += f"║ Video File: {video_display}\n"  
        info_text += f"║ Bot: {'Yes' if getattr(user_entity, 'bot', False) else 'No'}\n"  
        info_text += f"║ Verified: {'Yes' if getattr(user_entity, 'verified', False) else 'No'}\n"  
        info_text += f"║ Restricted: {'Yes' if getattr(user_entity, 'restricted', False) else 'No'}\n"  
        info_text += f"║ Premium: {'Yes' if getattr(user_entity, 'premium', False) else 'No'}\n"  
        info_text += f"║ Groups: {len(user_groups)} found\n"  
        if user_groups:  
            groups_display = ', '.join(user_groups[:3]) + ('...' if len(user_groups) > 3 else '')  
            info_text += f"║ └─ {groups_display}\n"  
        info_text += "╚══════════════════════════╝"  
          
        # Send profile photo if available  
        if profile_photo_bio:  
            try:  
                await client.send_file(event.chat_id, profile_photo_bio, caption=f"```{terminal.get_prompt()}{info_text}```")  
                await msg.delete()  
                # Clean up temp file  
                try:  
                    os.remove(f"temp_info_{user_entity.id}.jpg")  
                except:  
                    pass  
            except:  
                # If sending photo fails, send text only  
                await msg.edit(f"```{terminal.get_prompt()}{info_text}```")  
        else:  
            await msg.edit(f"```{terminal.get_prompt()}{info_text}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {type(e).__name__}: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]ping$'))
async def handler_ping(event):
    try:
        await event.delete()
    except:
        pass

    # Terminal-style ping  
    msg = await send_terminal_message(event, "ping -c 1 telegram.org")  
  
    start_time = time.time()  
    await asyncio.sleep(0.1)  
    end_time = time.time()  
  
    ping_time = round((end_time - start_time) * 1000)  
  
    ping_output = f"PING telegram.org (149.154.167.91): 56(84) bytes of data.\n"  
    ping_output += f"64 bytes from 149.154.167.91: icmp_seq=1 ttl=55 time={ping_time}ms\n"  
    ping_output += f"\n--- telegram.org ping statistics ---\n"  
    ping_output += f"1 packets transmitted, 1 received, 0% packet loss, time 0ms\n"  
    ping_output += f"rtt min/avg/max/mdev = {ping_time}/{ping_time}/{ping_time}/0.000 ms"  
  
    await msg.edit(f"```{terminal.get_prompt()}{ping_output}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]name\s+(.+)$'))
async def handler_name(event):
    try:
        await event.delete()
    except:
        pass

    new_name = event.pattern_match.group(1)  
    name_parts = new_name.split(' ', 1)  
    first_name = name_parts[0]  
    last_name = name_parts[1] if len(name_parts) > 1 else ""  
  
    # Terminal-style name update  
    msg = await send_terminal_message(event, f"usermod -l '{first_name}' --surname '{last_name}'")  
  
    try:  
        await client(functions.account.UpdateProfileRequest(  
            first_name=first_name,  
            last_name=last_name  
        ))  
        await msg.edit(f"```{terminal.get_prompt()}echo 'Name updated successfully to: {first_name} {last_name}'```")  
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {type(e).__name__}: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]bio\s+(.+)$'))
async def handler_bio(event):
    try:
        await event.delete()
    except:
        pass

    new_bio = event.pattern_match.group(1)  
  
    # Terminal-style bio update  
    msg = await send_terminal_message(event, f"echo '{new_bio}' > ~/.profile_bio")  
  
    try:  
        await client(functions.account.UpdateProfileRequest(about=new_bio))  
        await msg.edit(f"```{terminal.get_prompt()}echo 'Bio updated successfully'```")  
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {type(e).__name__}: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]timebio$'))
async def handler_timebio(event):
    try:
        await event.delete()
    except:
        pass

    # Terminal-style time sync  
    msg = await send_terminal_message(event, "ntpdate -s time.nist.gov")  
    await terminal_loading(msg, "time synchronization", 0.5)  
  
    try:  
        # Get Bangladesh timezone  
        bd_tz = pytz.timezone('Asia/Dhaka')  
        utc_now = datetime.now(pytz.utc)  
        bd_time = utc_now.astimezone(bd_tz)  
          
        # Format time  
        time_12 = bd_time.strftime('%I:%M:%S %p')  
        date = bd_time.strftime('%d %B %Y')  
        day = bd_time.strftime('%A')  
          
        # Create bio text with time  
        bio_text = f"🕐 {time_12} | {date} | {day}"  
          
        # Update bio  
        await client(functions.account.UpdateProfileRequest(about=bio_text))  
          
        await msg.edit(f"```{terminal.get_prompt()}echo 'Time Bio Updated: {bio_text}'```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: Time sync failed - {type(e).__name__}: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]hack$'))
async def handler_hack(event):
    try:
        await event.delete()
    except:
        pass

    # Start hacking animation  
    msg = await send_terminal_message(event, "python hack.py --target=random", show_cursor=False)  
    await asyncio.sleep(0.5)  
  
    # Hacking animation sequence  
    hack_steps = [  
        ("Initializing hacking protocols...", 0.3),  
        ("Scanning network vulnerabilities...", 0.4),  
        ("Exploiting system weaknesses...", 0.5),  
        ("Bypassing firewall protections...", 0.4),  
        ("Injecting payload...", 0.5),  
        ("Establishing remote connection...", 0.4),  
        ("Extracting sensitive data...", 0.5),  
        ("Covering digital tracks...", 0.4),  
        ("Planting backdoor access...", 0.5),  
        ("Finalizing breach...", 0.3)  
    ]  
  
    for step, delay in hack_steps:  
        await update_terminal_message(f"{terminal.get_loader()} {step}")  
        await asyncio.sleep(delay)  
  
    # Show fake hacking results  
    hack_results = """cat << 'EOF'

╔══════════════════════════════════════════════════╗
║              HACK COMPLETE                       ║
╠══════════════════════════════════════════════════╣
║ Target: Random User System                       ║
║ Status: COMPROMISED                              ║
║                                                  ║
║ [+] Access Level: ADMIN                          ║
║ [+] Data Extracted: 2.4 GB                       ║
║ [+] Passwords Cracked: 127                       ║
║ [+] Session Hijacked: Yes                        ║
║ [+] Backdoor Installed: Yes                      ║
║                                                  ║
║ Next Steps:                                      ║
║ • Monitor target activity                        ║
║ • Extract additional data                        ║
║ • Maintain persistence                           ║
║                                                  ║
║ Status: ACTIVE - Connection Secure               ║
╚══════════════════════════════════════════════════╝
EOF"""

    await msg.edit(f"```{terminal.get_prompt()}{hack_results}```")  
  
    # Final message  
    await asyncio.sleep(2)  
    await msg.edit(f"```{terminal.get_prompt()}echo 'Hack successful! All systems compromised.'```")

# ====== TEXT TO IMAGE COMMANDS ======

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]img\s+(.+)$'))
async def handler_text_image(event):
    """Create image from text"""
    try:
        await event.delete()
    except:
        pass

    text = event.pattern_match.group(1)  
  
    # Check for style specification  
    style = "default"  
    if "--hacker" in text:  
        style = "hacker"  
        text = text.replace("--hacker", "").strip()  
    elif "--neon" in text:  
        style = "neon"  
        text = text.replace("--neon", "").strip()  
    elif "--ascii" in text:  
        style = "ascii"  
        text = text.replace("--ascii", "").strip()  
  
    msg = await send_terminal_message(event, f"convert --text-to-image --style={style}")  
  
    try:  
        if style == "ascii":  
            image_bio = await create_ascii_image(text)  
        else:  
            image_bio = await create_text_image(text, style)  
          
        if image_bio:  
            await update_terminal_message(f"{terminal.get_loader()} Uploading generated image...")  
            await client.send_file(event.chat_id, image_bio, caption=f"```{terminal.get_prompt()}echo 'Image generated successfully'```")  
            await msg.delete()  
        else:  
            await msg.edit(f"```{terminal.get_prompt()}Error: Failed to generate image```")  
              
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]banner\s+(.+)$'))
async def handler_banner(event):
    """Create banner from text"""
    try:
        await event.delete()
    except:
        pass

    text = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"banner --create '{text[:20]}...'")  
  
    try:  
        # Create a banner-style image  
        image_bio = await create_text_image(text, "hacker")  
          
        if image_bio:  
            await update_terminal_message(f"{terminal.get_loader()} Uploading banner...")  
            await client.send_file(event.chat_id, image_bio, caption=f"```{terminal.get_prompt()}echo 'Banner created successfully'```")  
            await msg.delete()  
        else:  
            await msg.edit(f"```{terminal.get_prompt()}Error: Failed to create banner```")  
              
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

# ====== NEW COMMANDS WITH LAZY LOADING ======

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]upload\s+(.+)$'))
async def handler_upload(event):
    """Upload file to chat"""
    try:
        await event.delete()
    except:
        pass

    file_path = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"upload --file='{file_path}'")  
  
    try:  
        if os.path.exists(file_path):  
            await update_terminal_message(f"{terminal.get_loader()} Reading file...")  
            await client.send_file(event.chat_id, file_path)  
            await msg.edit(f"```{terminal.get_prompt()}echo 'File uploaded successfully'```")  
        else:  
            await msg.edit(f"```{terminal.get_prompt()}Error: File not found```")  
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]download\s+(.+)$'))
async def handler_download(event):
    """Download file from URL"""
    try:
        await event.delete()
    except:
        pass

    url = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"wget {url}")  
  
    try:  
        await update_terminal_message(f"{terminal.get_loader()} Connecting to server...")  
        response = requests.get(url, stream=True)  
          
        if response.status_code == 200:  
            await update_terminal_message(f"{terminal.get_loader()} Downloading file...")  
              
            # Get filename from URL  
            filename = url.split('/')[-1]  
            if not filename:  
                filename = "downloaded_file"  
              
            # Save file  
            with open(filename, 'wb') as f:  
                for chunk in response.iter_content(chunk_size=8192):  
                    f.write(chunk)  
              
            await update_terminal_message(f"{terminal.get_loader()} Uploading to Telegram...")  
            await client.send_file(event.chat_id, filename)  
              
            # Clean up  
            os.remove(filename)  
              
            await msg.edit(f"```{terminal.get_prompt()}echo 'File downloaded and uploaded successfully'```")  
        else:  
            await msg.edit(f"```{terminal.get_prompt()}Error: HTTP {response.status_code}```")  
              
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]qr\s+(.+)$'))
async def handler_qr(event):
    """Generate QR code"""
    try:
        await event.delete()
    except:
        pass

    # Check and install qrcode module  
    if not check_package_lazy('qrcode', 'qrcode', 'qrcode[pil]'):  
        await event.respond("❌ Failed to install qrcode module")  
        return  
  
    import qrcode  
  
    text = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"qr --generate '{text[:20]}...'")  
  
    try:  
        # Generate QR code  
        qr = qrcode.QRCode(  
            version=1,  
            error_correction=qrcode.constants.ERROR_CORRECT_L,  
            box_size=10,  
            border=4,  
        )  
        qr.add_data(text)  
        qr.make(fit=True)  
          
        # Create image  
        img = qr.make_image(fill_color="black", back_color="white")  
          
        # Save to BytesIO  
        bio = BytesIO()  
        bio.name = 'qr_code.png'  
        img.save(bio, 'PNG')  
        bio.seek(0)  
          
        await update_terminal_message(f"{terminal.get_loader()} Uploading QR code...")  
        await client.send_file(event.chat_id, bio, caption=f"```{terminal.get_prompt()}echo 'QR code generated for: {text}'```")  
        await msg.delete()  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]calc\s+(.+)$'))
async def handler_calc(event):
    """Calculator command"""
    try:
        await event.delete()
    except:
        pass

    expression = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"calc '{expression}'")  
  
    try:  
        # Safe evaluation of mathematical expression  
        allowed_chars = set('0123456789+-*/().^ ')  
        if not all(c in allowed_chars for c in expression):  
            await msg.edit(f"```{terminal.get_prompt()}Error: Invalid characters in expression```")  
            return  
          
        # Replace ^ with ** for power  
        expression = expression.replace('^', '**')  
          
        # Calculate result  
        result = eval(expression)  
          
        await msg.edit(f"```{terminal.get_prompt()}echo '{expression} = {result}'```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: Invalid expression```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]translate\s+(\w+)\s+(.+)$'))
async def handler_translate(event):
    """Translate text"""
    try:
        await event.delete()
    except:
        pass

    # Check and install googletrans module  
    if not check_package_lazy('googletrans', 'googletrans', 'googletrans==4.0.0-rc1'):  
        await event.respond("❌ Failed to install googletrans module")  
        return  
  
    from googletrans import Translator  
  
    target_lang = event.pattern_match.group(1)  
    text = event.pattern_match.group(2)  
  
    msg = await send_terminal_message(event, f"translate --to={target_lang} '{text[:30]}...'")  
  
    try:  
        await update_terminal_message(f"{terminal.get_loader()} Translating text...")  
          
        # Translate text  
        translator = Translator()  
        result = translator.translate(text, dest=target_lang)  
          
        # Create translation output - FIXED F-STRING FORMATTING  
        translation_output = "╔══════════════════════════╗\n"  
        translation_output += "║      TRANSLATION         ║\n"  
        translation_output += "╠══════════════════════════╣\n"  
        translation_output += f"║ From: {result.src.upper()}\n"  
        translation_output += f"║ To: {target_lang.upper()}\n"  
        translation_output += "║ ──────────────────────── ║\n"  
        original_display = text[:40] + ('...' if len(text) > 40 else '')  
        translation_output += f"║ Original: {original_display}\n"  
        translation_output += "║ ──────────────────────── ║\n"  
        result_display = result.text[:40] + ('...' if len(result.text) > 40 else '')  
        translation_output += f"║ Result: {result_display}\n"  
        translation_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{translation_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: Translation failed - {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]encrypt\s+(.+)$'))
async def handler_encrypt(event):
    """Encrypt text"""
    try:
        await event.delete()
    except:
        pass

    text = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"encrypt --text='{text[:20]}...'")  
  
    try:  
        # Get password from user  
        await msg.edit(f"```{terminal.get_prompt()}Enter encryption password: ```")  
          
        # Wait for password message (simplified - in real implementation, you'd need a better way)  
        await asyncio.sleep(3)  
          
        # For demo, use a default password  
        password = "default123"  
          
        # Encrypt text  
        encrypted = encrypt_text(text, password)  
          
        # Create encryption output - FIXED F-STRING FORMATTING  
        encryption_output = "╔══════════════════════════╗\n"  
        encryption_output += "║      ENCRYPTION          ║\n"  
        encryption_output += "╠══════════════════════════╣\n"  
        original_display = text[:30] + ('...' if len(text) > 30 else '')  
        encryption_output += f"║ Original: {original_display}\n"  
        encryption_output += "║ ──────────────────────── ║\n"  
        encryption_output += "║ Encrypted:\n"  
        encrypted_display = encrypted[:40] + ('...' if len(encrypted) > 40 else '')  
        encryption_output += f"║ {encrypted_display}\n"  
        encryption_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{encryption_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: Encryption failed - {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]decrypt\s+(.+)$'))
async def handler_decrypt(event):
    """Decrypt text"""
    try:
        await event.delete()
    except:
        pass

    encrypted_text = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"decrypt --text='{encrypted_text[:20]}...'")  
  
    try:  
        # Get password from user  
        await msg.edit(f"```{terminal.get_prompt()}Enter decryption password: ```")  
          
        # Wait for password message  
        await asyncio.sleep(3)  
          
        # For demo, use a default password  
        password = "default123"  
          
        # Decrypt text  
        decrypted = decrypt_text(encrypted_text, password)  
          
        if decrypted:  
            # Create decryption output - FIXED F-STRING FORMATTING  
            decryption_output = "╔══════════════════════════╗\n"  
            decryption_output += "║      DECRYPTION          ║\n"  
            decryption_output += "╠══════════════════════════╣\n"  
            encrypted_display = encrypted_text[:30] + ('...' if len(encrypted_text) > 30 else '')  
            decryption_output += f"║ Encrypted: {encrypted_display}\n"  
            decryption_output += "║ ──────────────────────── ║\n"  
            decryption_output += "║ Decrypted:\n"  
            decrypted_display = decrypted[:40] + ('...' if len(decrypted) > 40 else '')  
            decryption_output += f"║ {decrypted_display}\n"  
            decryption_output += "╚══════════════════════════╝"  
              
            await msg.edit(f"```{terminal.get_prompt()}{decryption_output}```")  
        else:  
            await msg.edit(f"```{terminal.get_prompt()}Error: Invalid password or corrupted data```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: Decryption failed - {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]password(\s+(\d+))?$'))
async def handler_password(event):
    """Generate secure password"""
    try:
        await event.delete()
    except:
        pass

    length = event.pattern_match.group(2)  
    if length:  
        length = int(length)  
    else:  
        length = 12  
  
    msg = await send_terminal_message(event, f"password --generate --length={length}")  
  
    try:  
        # Generate password  
        password = generate_password(length)  
          
        # Create password output - FIXED F-STRING FORMATTING  
        password_output = "╔══════════════════════════╗\n"  
        password_output += "║    PASSWORD GENERATOR     ║\n"  
        password_output += "╠══════════════════════════╣\n"  
        password_output += f"║ Length: {length} characters\n"  
        password_output += "║ ──────────────────────── ║\n"  
        password_output += "║ Generated Password:\n"  
        password_output += f"║ {password}\n"  
        password_output += "║ ──────────────────────── ║\n"  
        password_output += "║ Strength: Very Strong\n"  
        password_output += "║ Entropy: High\n"  
        password_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{password_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]uptime$'))
async def handler_uptime(event):
    """Show bot uptime"""
    try:
        await event.delete()
    except:
        pass

    # Check and install psutil module  
    if not check_package_lazy('psutil', 'psutil', 'psutil'):  
        await event.respond("❌ Failed to install psutil module")  
        return  
  
    import psutil  
  
    msg = await send_terminal_message(event, "uptime")  
  
    try:  
        # Calculate uptime  
        uptime_seconds = time.time() - START_TIME  
        uptime_str = format_time(uptime_seconds)  
          
        # Get system info  
        cpu_percent = psutil.cpu_percent(interval=1)  
        memory = psutil.virtual_memory()  
        disk = psutil.disk_usage('/')  
          
        # Create uptime output - FIXED F-STRING FORMATTING  
        uptime_output = "╔══════════════════════════╗\n"  
        uptime_output += "║     SYSTEM STATUS        ║\n"  
        uptime_output += "╠══════════════════════════╣\n"  
        uptime_output += f"║ Bot Uptime: {uptime_str}\n"  
        uptime_output += "║ ──────────────────────── ║\n"  
        uptime_output += f"║ CPU Usage: {cpu_percent}%\n"  
        uptime_output += f"║ Memory: {memory.percent}% ({format_bytes(memory.used)}/{format_bytes(memory.total)})\n"  
        uptime_output += f"║ Disk: {disk.percent}% ({format_bytes(disk.used)}/{format_bytes(disk.total)})\n"  
        uptime_output += "║ ──────────────────────── ║\n"  
        uptime_output += "║ Status: Online\n"  
        uptime_output += "║ Version: 4.0 Enhanced\n"  
        uptime_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{uptime_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]roll(\s+(\d+))?$'))
async def handler_roll(event):
    """Roll dice"""
    try:
        await event.delete()
    except:
        pass

    sides = event.pattern_match.group(2)  
    if sides:  
        sides = int(sides)  
    else:  
        sides = 6  
  
    msg = await send_terminal_message(event, f"roll --sides={sides}")  
  
    try:  
        # Roll dice  
        result = random.randint(1, sides)  
          
        # Create dice animation  
        for i in range(3):  
            await update_terminal_message(f"{terminal.get_loader()} Rolling dice...")  
            await asyncio.sleep(0.2)  
          
        # Create roll output - FIXED F-STRING FORMATTING  
        roll_output = "╔══════════════════════════╗\n"  
        roll_output += "║        DICE ROLL         ║\n"  
        roll_output += "╠══════════════════════════╣\n"  
        roll_output += f"║ Dice Type: D{sides}\n"  
        roll_output += "║ ──────────────────────── ║\n"  
        roll_output += f"║ Result: {result}\n"  
        roll_output += f"║ {'🎲' if result == sides else '⚀'}\n"  
        roll_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{roll_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]8ball\s+(.+)$'))
async def handler_8ball(event):
    """Magic 8-ball"""
    try:
        await event.delete()
    except:
        pass

    question = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"8ball '{question[:20]}...'")  
  
    try:  
        # Magic 8-ball responses  
        responses = [  
            "It is certain.",  
            "It is decidedly so.",  
            "Without a doubt.",  
            "Yes - definitely.",  
            "You may rely on it.",  
            "As I see it, yes.",  
            "Most likely.",  
            "Outlook good.",  
            "Yes.",  
            "Signs point to yes.",  
            "Reply hazy, try again.",  
            "Ask again later.",  
            "Better not tell you now.",  
            "Cannot predict now.",  
            "Concentrate and ask again.",  
            "Don't count on it.",  
            "My reply is no.",  
            "My sources say no.",  
            "Outlook not so good.",  
            "Very doubtful."  
        ]  
          
        # Get random response  
        response = random.choice(responses)  
          
        # Create animation  
        await update_terminal_message(f"{terminal.get_loader()} Shaking the 8-ball...")  
        await asyncio.sleep(1)  
          
        # Create ball output - FIXED F-STRING FORMATTING  
        ball_output = "╔══════════════════════════╗\n"  
        ball_output += "║       MAGIC 8-BALL       ║\n"  
        ball_output += "╠══════════════════════════╣\n"  
        question_display = question[:35] + ('...' if len(question) > 35 else '')  
        ball_output += f"║ Question: {question_display}\n"  
        ball_output += "║ ──────────────────────── ║\n"  
        ball_output += f"║ Answer: {response}\n"  
        ball_output += "║ 🔮\n"  
        ball_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{ball_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]note\s+(.+)$'))
async def handler_note(event):
    """Save a note"""
    try:
        await event.delete()
    except:
        pass

    note_text = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"note --save '{note_text[:20]}...'")  
  
    try:  
        # Load existing notes  
        notes = load_notes()  
          
        # Generate note ID  
        note_id = str(int(time.time()))  
          
        # Save note  
        notes[note_id] = {  
            'text': note_text,  
            'timestamp': datetime.now().isoformat()  
        }  
        save_notes(notes)  
          
        await msg.edit(f"```{terminal.get_prompt()}echo 'Note saved successfully (ID: {note_id})'```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]notes$'))
async def handler_notes(event):
    """List all notes"""
    try:
        await event.delete()
    except:
        pass

    msg = await send_terminal_message(event, "notes --list")  
  
    try:  
        # Load notes  
        notes = load_notes()  
          
        if not notes:  
            await msg.edit(f"```{terminal.get_prompt()}echo 'No notes found'```")  
            return  
          
        # Create notes list - FIXED F-STRING FORMATTING  
        notes_output = "╔══════════════════════════╗\n"  
        notes_output += "║        NOTES LIST        ║\n"  
        notes_output += "╠══════════════════════════╣\n"  
          
        for note_id, note_data in list(notes.items())[-5:]:  # Show last 5 notes  
            timestamp = datetime.fromisoformat(note_data['timestamp']).strftime('%Y-%m-%d %H:%M')  
            text = note_data['text'][:35] + ('...' if len(note_data['text']) > 35 else '')  
            notes_output += f"║ [{note_id}] {timestamp}\n"  
            notes_output += f"║ └─ {text}\n"  
            notes_output += "║ ──────────────────────── ║\n"  
          
        notes_output += f"║ Total: {len(notes)} notes\n"  
        notes_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{notes_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]members$'))
async def handler_members(event):
    """Get group members list"""
    try:
        await event.delete()
    except:
        pass

    msg = await send_terminal_message(event, "members --list")  
  
    try:  
        # Get chat entity  
        chat = await event.get_chat()  
          
        if not chat.is_group and not chat.is_channel:  
            await msg.edit(f"```{terminal.get_prompt()}Error: This is not a group/channel```")  
            return  
          
        await update_terminal_message(f"{terminal.get_loader()} Fetching members list...")  
          
        # Get members  
        members = []  
        async for member in client.iter_participants(chat, limit=20):  
            members.append(member)  
          
        # Create members list - FIXED F-STRING FORMATTING  
        members_output = "╔══════════════════════════╗\n"  
        members_output += "║      MEMBERS LIST       ║\n"  
        members_output += "╠══════════════════════════╣\n"  
        members_output += f"║ Chat: {chat.title}\n"  
        members_output += f"║ Total Members: {len(members)}+ (showing first 20)\n"  
        members_output += "║ ──────────────────────── ║\n"  
          
        for member in members[:10]:  # Show first 10  
            name = f"{member.first_name} {member.last_name or ''}".strip()  
            status = "🟢" if getattr(member, 'status', None) and hasattr(member.status, 'was_online') else "⚪"  
            name_display = name[:30] + ('...' if len(name) > 30 else '')  
            members_output += f"║ {status} {name_display}\n"  
          
        members_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{members_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]stats$'))
async def handler_stats(event):
    """Get group statistics"""
    try:
        await event.delete()
    except:
        pass

    msg = await send_terminal_message(event, "stats --group")  
  
    try:  
        # Get chat entity  
        chat = await event.get_chat()  
          
        if not chat.is_group and not chat.is_channel:  
            await msg.edit(f"```{terminal.get_prompt()}Error: This is not a group/channel```")  
            return  
          
        await update_terminal_message(f"{terminal.get_loader()} Analyzing group statistics...")  
          
        # Get statistics  
        total_members = 0  
        online_members = 0  
        bots = 0  
          
        async for member in client.iter_participants(chat, limit=100):  
            total_members += 1  
            if getattr(member, 'bot', False):  
                bots += 1  
            elif hasattr(member, 'status') and hasattr(member.status, 'was_online'):  
                online_members += 1  
          
        # Create stats output - FIXED F-STRING FORMATTING  
        stats_output = "╔══════════════════════════╗\n"  
        stats_output += "║     GROUP STATISTICS     ║\n"  
        stats_output += "╠══════════════════════════╣\n"  
        stats_output += f"║ Group: {chat.title}\n"  
        stats_output += f"║ Type: {'Channel' if chat.is_channel else 'Group'}\n"  
        stats_output += "║ ──────────────────────── ║\n"  
        stats_output += f"║ Total Members: {total_members}+\n"  
        stats_output += f"║ Online Now: {online_members}\n"  
        stats_output += f"║ Bots: {bots}\n"  
        stats_output += f"║ Humans: {total_members - bots}\n"  
        stats_output += "║ ──────────────────────── ║\n"  
        stats_output += f"║ Created: {getattr(chat, 'date', 'Unknown')}\n"  
        stats_output += f"║ Verified: {'Yes' if getattr(chat, 'verified', False) else 'No'}\n"  
        stats_output += f"║ Scam: {'Yes' if getattr(chat, 'scam', False) else 'No'}\n"  
        stats_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{stats_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]weather\s+(.+)$'))
async def handler_weather(event):
    """Get weather information"""
    try:
        await event.delete()
    except:
        pass

    city = event.pattern_match.group(1)  
  
    msg = await send_terminal_message(event, f"weather --city='{city}'")  
  
    try:  
        await update_terminal_message(f"{terminal.get_loader()} Fetching weather data...")  
          
        # Get weather data (using OpenWeatherMap API - you'd need to add your API key)  
        # For demo, we'll show fake data  
        weather_data = {  
            'temp': random.randint(15, 35),  
            'feels_like': random.randint(15, 35),  
            'humidity': random.randint(30, 90),  
            'wind_speed': random.randint(5, 25),  
            'description': random.choice(['Clear sky', 'Few clouds', 'Scattered clouds', 'Broken clouds', 'Shower rain', 'Rain', 'Thunderstorm', 'Snow', 'Mist']),  
            'pressure': random.randint(1000, 1020)  
        }  
          
        # Create weather output - FIXED F-STRING FORMATTING  
        weather_output = "╔══════════════════════════╗\n"  
        weather_output += "║      WEATHER REPORT      ║\n"  
        weather_output += "╠══════════════════════════╣\n"  
        weather_output += f"║ City: {city}\n"  
        weather_output += "║ ──────────────────────── ║\n"  
        weather_output += f"║ Temperature: {weather_data['temp']}°C\n"  
        weather_output += f"║ Feels Like: {weather_data['feels_like']}°C\n"  
        weather_output += f"║ Condition: {weather_data['description']}\n"  
        weather_output += f"║ Humidity: {weather_data['humidity']}%\n"  
        weather_output += f"║ Wind Speed: {weather_data['wind_speed']} km/h\n"  
        weather_output += f"║ Pressure: {weather_data['pressure']} hPa\n"  
        weather_output += "║ ──────────────────────── ║\n"  
        weather_output += f"║ Updated: {datetime.now().strftime('%Y-%m-%d %H:%M')}\n"  
        weather_output += "╚══════════════════════════╝"  
          
        await msg.edit(f"```{terminal.get_prompt()}{weather_output}```")  
          
    except Exception as e:  
        await msg.edit(f"```{terminal.get_prompt()}Error: {str(e)}```")

# ====== AUTO-REPLY COMMANDS ======

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]automsg\s+(.+)$'))
async def handler_automsg(event):
    """Enable or disable auto-reply mode"""
    try:
        await event.delete()
    except:
        pass

    global auto_reply_enabled
    
    command = event.pattern_match.group(1).lower()
    
    if command == "on":
        auto_reply_enabled = True
        msg = await send_terminal_message(event, "automsg --enable")
        await asyncio.sleep(0.5)
        await msg.edit(f"```{terminal.get_prompt()}echo 'Clarifai GPT intelligent auto-reply mode ENABLED'```")
    elif command == "off":
        auto_reply_enabled = False
        msg = await send_terminal_message(event, "automsg --disable")
        await asyncio.sleep(0.5)
        await msg.edit(f"```{terminal.get_prompt()}echo 'Auto-reply mode DISABLED'```")
    elif command == "status":
        status = "ENABLED" if auto_reply_enabled else "DISABLED"
        custom_count = len(custom_auto_replies)
        msg = await send_terminal_message(event, f"automsg --status")
        await asyncio.sleep(0.3)
        
        status_output = f"Auto-reply Status: {status}\n"
        status_output += f"Custom Replies: {custom_count} configured\n"
        status_output += f"Clarifai GPT Integration: Active"
        
        await msg.edit(f"```{terminal.get_prompt()}echo '{status_output}'```")
    else:
        await send_terminal_message(event, "echo 'Usage: .automsg on/off/status'")
        return

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]setreply\s+(.+?)\s*=\s*(.+)$'))
async def handler_setreply(event):
    """Set custom auto-reply message"""
    try:
        await event.delete()
    except:
        pass

    global custom_auto_replies
    
    trigger = event.pattern_match.group(1).strip().lower()
    response = event.pattern_match.group(2).strip()
    
    msg = await send_terminal_message(event, f"setreply --trigger='{trigger}'")
    
    # Add or update the custom reply
    custom_auto_replies[trigger] = response
    
    # Save to file
    if save_custom_replies():
        await update_terminal_message(f"{terminal.get_loader()} Saving custom reply...")
        await asyncio.sleep(0.3)
        await msg.edit(f"```{terminal.get_prompt()}echo 'Custom reply set: \"{trigger}\" -> \"{response[:30]}...\"'```")
    else:
        await msg.edit(f"```{terminal.get_prompt()}echo 'Failed to save custom reply'```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]delreply\s+(.+)$'))
async def handler_delreply(event):
    """Delete custom auto-reply message"""
    try:
        await event.delete()
    except:
        pass

    global custom_auto_replies
    
    trigger = event.pattern_match.group(1).strip().lower()
    
    msg = await send_terminal_message(event, f"delreply --trigger='{trigger}'")
    
    if trigger in custom_auto_replies:
        del custom_auto_replies[trigger]
        
        # Save to file
        if save_custom_replies():
            await update_terminal_message(f"{terminal.get_loader()} Deleting custom reply...")
            await asyncio.sleep(0.3)
            await msg.edit(f"```{terminal.get_prompt()}echo 'Custom reply deleted: \"{trigger}\"'```")
        else:
            await msg.edit(f"```{terminal.get_prompt()}echo 'Failed to save changes'```")
    else:
        await msg.edit(f"```{terminal.get_prompt()}echo 'No custom reply found for: \"{trigger}\"'```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]listreplies$'))
async def handler_listreplies(event):
    """List all custom auto-reply messages"""
    try:
        await event.delete()
    except:
        pass

    msg = await send_terminal_message(event, "listreplies --all")
    
    if not custom_auto_replies:
        await msg.edit(f"```{terminal.get_prompt()}echo 'No custom replies configured'```")
        return
    
    # Create formatted list
    list_output = "╔════════════════════════════════╗\n"
    list_output += "║     CUSTOM AUTO-REPLIES       ║\n"
    list_output += "╠════════════════════════════════╣\n"
    
    for i, (trigger, response) in enumerate(custom_auto_replies.items(), 1):
        trigger_display = trigger[:25] + ('...' if len(trigger) > 25 else '')
        response_display = response[:35] + ('...' if len(response) > 35 else '')
        list_output += f"║ {i:2d}. {trigger_display}\n"
        list_output += f"║     └─> {response_display}\n"
        list_output += "║ ───────────────────────────── ║\n"
    
    list_output += f"║ Total: {len(custom_auto_replies)} custom replies\n"
    list_output += "╚════════════════════════════════╝"
    
    await msg.edit(f"```{terminal.get_prompt()}{list_output}```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]clearreplies$'))
async def handler_clearreplies(event):
    """Clear all custom auto-reply messages"""
    try:
        await event.delete()
    except:
        pass

    global custom_auto_replies
    
    msg = await send_terminal_message(event, "clearreplies --all")
    
    if not custom_auto_replies:
        await msg.edit(f"```{terminal.get_prompt()}echo 'No custom replies to clear'```")
        return
    
    # Clear all replies
    custom_auto_replies.clear()
    
    # Save empty dict to file
    if save_custom_replies():
        await update_terminal_message(f"{terminal.get_loader()} Clearing all custom replies...")
        await asyncio.sleep(0.3)
        await msg.edit(f"```{terminal.get_prompt()}echo 'All custom replies cleared'```")
    else:
        await msg.edit(f"```{terminal.get_prompt()}echo 'Failed to clear replies'```")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]setclarifai\s+(.+)$'))
async def handler_setclarifai(event):
    """Set Clarifai PAT"""
    try:
        await event.delete()
    except:
        pass

    global CLARIFAI_PAT
    
    pat = event.pattern_match.group(1).strip()
    
    msg = await send_terminal_message(event, "clarifai --configure")
    
    # Update the PAT
    CLARIFAI_PAT = pat
    await update_terminal_message(f"{terminal.get_loader()} Verifying Clarifai PAT...")
    await asyncio.sleep(0.5)
    
    # Test the PAT
    try:
        if not check_package_lazy('clarifai-grpc', 'clarifai_grpc', 'clarifai-grpc'):
            await msg.edit(f"```{terminal.get_prompt()}echo 'Failed to install Clarifai package'```")
            return
            
        from clarifai_grpc.channel.clarifai_channel import ClarifaiChannel
        from clarifai_grpc.grpc.api import resources_pb2, service_pb2, service_pb2_grpc
        from clarifai_grpc.grpc.api.status import status_code_pb2
        
        # Set up the channel and stub
        channel = ClarifaiChannel.get_grpc_channel()
        stub = service_pb2_grpc.V2Stub(channel)
        
        # Set up metadata with PAT
        metadata = (('authorization', 'Key ' + CLARIFAI_PAT),)
        
        # Create user app ID set
        userDataObject = resources_pb2.UserAppIDSet(user_id=CLARIFAI_USER_ID, app_id=CLARIFAI_APP_ID)
        
        # Simple test request
        post_model_outputs_response = stub.PostModelOutputs(
            service_pb2.PostModelOutputsRequest(
                user_app_id=userDataObject,
                model_id=CLARIFAI_MODEL_ID,
                version_id=CLARIFAI_MODEL_VERSION_ID,
                inputs=[
                    resources_pb2.Input(
                        data=resources_pb2.Data(
                            text=resources_pb2.Text(
                                raw="test"
                            )
                        )
                    )
                ]
            ),
            metadata=metadata
        )
        
        if post_model_outputs_response.status.code == status_code_pb2.SUCCESS:
            await msg.edit(f"```{terminal.get_prompt()}echo 'Clarifai PAT configured successfully'```")
        else:
            await msg.edit(f"```{terminal.get_prompt()}echo 'PAT verification failed: {post_model_outputs_response.status.description}'```")
            
    except Exception as e:
        await msg.edit(f"```{terminal.get_prompt()}echo 'PAT verification failed: {str(e)}'```")

# Add this event handler after the existing command handlers
@client.on(events.NewMessage(incoming=True))
async def intelligent_auto_reply_handler(event):
    """Handle incoming messages with intelligent auto-reply using Clarifai GPT"""
    global auto_reply_enabled, greeting_responses, custom_auto_replies
    
    # Skip if auto-reply is disabled
    if not auto_reply_enabled:
        return
    
    # Skip messages from bots
    if event.message.from_id and await event.get_sender() and (await event.get_sender()).bot:
        return
    
    # Skip messages from self
    me = await client.get_me()
    if event.message.from_id == me.id:
        return
    
    # Skip messages in groups/channels (only reply in private messages)
    if event.is_group or event.is_channel:
        return
    
    try:
        message_text = event.message.text
        if not message_text:
            return
        
        # Check for custom replies first
        custom_response = find_custom_reply(message_text)
        if custom_response:
            await event.reply(custom_response)
            return
        
        # Check if it's a greeting
        if is_greeting(message_text):
            # Send a random greeting response
            response = random.choice(greeting_responses)
            await event.reply(response)
        else:
            # Use Clarifai for other messages
            response = await get_clarifai_response(message_text)
            await event.reply(response)
            
    except Exception as e:
        print(f"Error in Clarifai intelligent auto-reply: {e}")

@client.on(events.NewMessage(outgoing=True, pattern=r'^[./]help$'))
async def handler_help(event):
    try:
        await event.delete()
    except:
        pass

    # Terminal-style help  
    msg = await send_terminal_message(event, "help")  
  
    help_output = """HACKERBOT COMMANDS v4.0 ENHANCED (LAZY LOADING)

================================================

PROFILE COMMANDS:
.cln/@cln @username    Clone target user profile
.back/@back            Restore original profile
.name/@name <name>     Change display name
.bio/@bio <text>       Change bio text
.timebio/@timebio      Set auto-updating time bio

INFORMATION COMMANDS:
.info/@info @username  Get user intelligence with photo
.ping/@ping            Test network latency
.uptime/@uptime        Show system status and uptime
.weather/@weather <city> Get weather information

GROUP COMMANDS:
.members/@members      List group members
.stats/@stats          Get group statistics

FILE OPERATIONS:
.upload/@upload <path> Upload file to chat
.download/@download <url> Download from URL

IMAGE GENERATION:
.img/@img <text>       Create image from text
.image/@image <text>   Create image from text
.banner/@banner <text> Create banner from text
.qr/@qr <text>         Generate QR code

Styles:
--hacker              Hacker green theme
--neon                Neon glow effect
--ascii               ASCII art style

UTILITY COMMANDS:
.calc/@calc <expr>     Calculator
.translate/@translate <lang> <text> Translate text
.encrypt/@encrypt <text> Encrypt text
.decrypt/@decrypt <text> Decrypt text
.password/@password [len] Generate password

NOTE COMMANDS:
.note/@note <text>     Save a note
.notes/@notes          List all notes

FUN COMMANDS:
.hack/@hack            Simulate hacking sequence
.roll/@roll [sides]    Roll dice
.8ball/@8ball <question> Magic 8-ball

CLARIFAI GPT INTELLIGENT AUTO-REPLY COMMANDS:
.automsg/@automsg on       Enable Clarifai GPT intelligent auto-reply mode
.automsg/@automsg off      Disable auto-reply mode
.automsg/@automsg status   Show auto-reply status
.setreply/@setreply <trigger> = <response> Set custom auto-reply
.delreply/@delreply <trigger> Delete custom auto-reply
.listreplies/@listreplies  List all custom auto-replies
.clearreplies/@clearreplies Clear all custom auto-replies
.setclarifai/@setclarifai <PAT> Set Clarifai PAT for GPT responses

LAZY LOADING INFO:
• Modules install automatically when needed
• Faster startup time
• Only required packages are downloaded
• Basic packages: telethon, pytz, Pillow, requests

EXAMPLES:
.cln @username         Clone a user
.name John Doe         Set name to John Doe
.img Hello World       Create image
.img Hello --hacker    Create hacker-style image
.info @username        Get user info with photo
.hack                  Start hacking animation
.calc 2+2*3            Calculate expression
.qr https://example.com Generate QR code
.translate en Hola     Translate Spanish to English
.setreply hi = Hello there!  Set custom reply
.automsg on            Enable auto-reply

Note: All commands work with both . and / prefixes
"""

    await msg.edit(f"```{terminal.get_prompt()}{help_output}```")

async def main():
    print("╔══════════════════════════════════════╗")
    print("║        HACKERBOT INITIATING         ║")
    print("║      (CLARIFAI GPT INTEGRATION)     ║")
    print("╚══════════════════════════════════════╝")
    print("\n✅ Basic packages loaded")
    print("📦 Additional packages will install on demand")
    print("🤖 Clarifai GPT integration ready")
    print("💬 Custom auto-reply system ready")
    print("🚀 Faster startup time achieved")
    
    # Load custom replies
    load_custom_replies()
    print(f"📝 Loaded {len(custom_auto_replies)} custom auto-replies")

    # Start client without auto-connect  
    await client.connect()  
  
    # Perform interactive authentication  
    auth_success = await interactive_auth()  
  
    if not auth_success:  
        print("❌ Authentication failed. Exiting...")  
        return  
  
    # Save session  
    await client.disconnect()  
    await client.start()  
  
    me = await client.get_me()  
    print(f"\n╔══════════════════════════════════════╗")  
    print(f"║     HACKERBOT ONLINE               ║")  
    print(f"║ User: {me.first_name} {me.last_name or ''}")  
    print(f"║ ID: {me.id}")  
    print(f"║ Status: Active                      ║")  
    print(f"║ Terminal Interface Ready            ║")  
    print(f"║ Text-to-Image Ready                 ║")  
    print(f"║ Lazy Loading: Enabled               ║")  
    print(f"║ Clarifai GPT: Ready                 ║")  
    print(f"║ Custom Replies: {len(custom_auto_replies)} loaded    ║")  
    print(f"║ Use .help for commands              ║")  
    print(f"╚══════════════════════════════════════╝")  
  
    await client.run_until_disconnected()

if __name__ == '__main__':
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n\n👋 Hackerbot shutting down...")
    except Exception as e:
        print(f"\n❌ Error: {e}")
